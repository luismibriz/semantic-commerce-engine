# Semantic Commerce Engine

AI-powered semantic product discovery engine for ecommerce platforms.

Built with Symfony, Hexagonal Architecture, DDD, CQRS, PostgreSQL and vector search.

## License

This project is licensed under the MIT License.

## Disclaimer

This is an independent portfolio project focused on backend architecture, semantic ecommerce search and AI-assisted software development.

It is not affiliated with any brand, company or recruitment process.

---

## Índice

- [Evaluación rápida](#evaluación-rápida)
- [Arquitectura](#arquitectura)
- [Modelado del dominio](#modelado-del-dominio)
- [API / OpenAPI](#api)
- [Levantar el entorno con Docker](#levantar-el-entorno-con-docker)
- [Ejecutar los tests](#ejecutar-los-tests)
- [Rendimiento](#rendimiento)
- [Documentación de diseño](#documentación-de-diseño)

---

## Evaluación rápida

Cuatro comandos para revisar el proyecto sin leer todo el README. Requisitos:
Docker con Compose; nada más (ni PHP ni Composer en el host).

```bash
# 1. Levantar el stack y sembrar un catálogo demo de 12 productos.
docker compose up --build -d

# 2. Probar la búsqueda semántica con una consulta intencional.
curl 'http://localhost:8080/api/products/search?q=ropa%20roja%20para%20el%20fr%C3%ADo&limit=3'

# 3. Quality gate completo — php-cs-fixer + PHPStan (nivel 6) + PHPUnit
#    (87 tests, 171 aserciones, incl. integration contra Postgres + ES).
docker compose exec app composer quality

# 4. Benchmark de latencia end-to-end por backend de búsqueda.
docker compose exec app php bin/console app:benchmark --products=300 --searches=300
```

El paso 1 deja la API en `http://localhost:8080` con catálogo demo precargado
(`docker/entrypoint.sh` ejecuta migraciones, prepara el índice y siembra
productos). Los tests del paso 3 corren contra una base de datos separada
(`app_test`, creada automáticamente la primera vez), así que no contaminan el
catálogo que estás probando con `curl`.

### Variantes para levantar

**Con embeddings reales de OpenAI** — por defecto se usa el generador offline
`hashing` (determinista, sin API key). Para búsqueda semántica real:

```bash
EMBEDDING_PROVIDER=openai OPENAI_API_KEY=sk-... docker compose up --build -d
```

**Cambiar el almacén de vectores** — por defecto Elasticsearch
(`dense_vector` + `knn`). Para usar PostgreSQL + `pgvector` (HNSW + coseno)
basta una variable, sin más cambios; el dominio, los casos de uso, los
endpoints y los tests no se tocan:

```bash
# Elasticsearch (por defecto, equivalente a no pasar la variable)
SEARCH_BACKEND=elasticsearch docker compose up --build -d

# PostgreSQL + pgvector
SEARCH_BACKEND=pgvector docker compose up --build -d
```

Las dos variantes se combinan: `SEARCH_BACKEND=pgvector EMBEDDING_PROVIDER=openai
OPENAI_API_KEY=sk-... docker compose up --build -d`. La discusión del
compromiso entre backends está en [`ai/DECISIONS.md`](ai/DECISIONS.md), y los
números medidos en cada uno en [Rendimiento](#rendimiento).

### Probar el API con Postman

La colección [`postman/semantic-commerce-engine.postman_collection.json`](postman/semantic-commerce-engine.postman_collection.json)
trae los endpoints listos para usar contra `http://localhost:8080` (variable
de colección `baseUrl`). Cubre:

- `Health` — sonda de disponibilidad.
- `Index products` — indexar tres productos y reindexarlos (idempotente).
- `Search` — dos búsquedas semánticas con resultados ordenados por relevancia.
- `Validation errors (sad paths)` — cuatro requests que disparan los `400`
  documentados (divisa inválida, precio negativo, query vacía, `limit` fuera
  de rango).

Importa el JSON en Postman y lanza los requests de arriba abajo; los tests
embebidos verifican el contrato (códigos HTTP, forma del JSON, presencia de
`score`, etc.).

Las siguientes secciones desarrollan cada bloque.

---

## Arquitectura

**Arquitectura hexagonal.** Las dependencias apuntan **hacia dentro**; el
dominio no conoce el exterior.

```
  Infrastructure
  HTTP · Console · Doctrine · Elasticsearch · pgvector ·
  OpenAI · Hashing · Symfony Messenger
      │
      ▼  implementan los puertos
  ┌───────────────────────────────────┐
  │ Application                       │
  │   IndexProductHandler             │
  │   RemoveProductHandler            │
  │   SearchProductsHandler           │
  │   Command / Query / Event buses   │
  │                                   │
  │   ┌───────────────────────────┐   │
  │   │ Domain                    │   │
  │   │   Product, Money,         │   │
  │   │   EmbeddingVector,        │   │
  │   │   SearchQuery, …          │   │
  │   │                           │   │
  │   │ Ports:                    │   │
  │   │   ProductRepository       │   │
  │   │   EmbeddingGenerator      │   │
  │   │   SemanticProductSearch   │   │
  │   │   ProductSearchIndex      │   │
  │   │   Clock                   │   │
  │   └───────────────────────────┘   │
  └───────────────────────────────────┘
```

**CQRS con un read store separado:**

- **Modelo de escritura** — el agregado `Product`, persistido en
  **PostgreSQL** (la única fuente de verdad) mediante Doctrine.
- **Modelo de lectura** — los productos con sus vectores en un almacén de
  búsqueda dedicado, usado en exclusiva para rankear.
- Tres buses de Symfony Messenger: `command.bus`, `query.bus`, `event.bus`.
- Indexar un producto registra un **evento de dominio** `ProductWasIndexed`;
  un proyector lo escucha y construye el modelo de lectura. El caso de uso de
  escritura nunca sabe que existe un read store.

### Almacén de vectores intercambiable

El lado de lectura solo se alcanza a través de dos puertos del dominio —
adaptadores intercambiables los implementan, seleccionados con la variable de
entorno **`SEARCH_BACKEND`**:

| `SEARCH_BACKEND`              | Backend                 | Ranking                                   |
| ----------------------------- | ----------------------- | ----------------------------------------- |
| `elasticsearch` (por defecto) | Elasticsearch           | `dense_vector` + `knn` aproximado, coseno |
| `pgvector`                    | PostgreSQL + `pgvector` | columna `vector(N)` + índice HNSW, coseno |

Cambiar el valor es el **único** cambio necesario: el dominio, los casos de
uso, los endpoints HTTP y la suite de tests no se tocan. Esa es la prueba de
que el almacén de vectores es un detalle de infraestructura, no una decisión
arquitectónica. La elección y sus compromisos se discuten en
[`ai/DECISIONS.md`](ai/DECISIONS.md).

### Generación de embeddings

El modelo de embeddings vive tras el puerto `EmbeddingGenerator`. Dos
adaptadores:

| Adaptador                   | Uso                                                                         |
| --------------------------- | --------------------------------------------------------------------------- |
| `OpenAiEmbeddingGenerator`  | Producción — embeddings semánticos reales (`text-embedding-3-small`).       |
| `HashingEmbeddingGenerator` | Offline y determinista. Por defecto en Docker y tests: arranca sin API key. |

---

## Modelado del dominio

*Bounded context*: **Catalog**.

**Raíz de agregado — `Product`**
`ProductId` · `ProductName` · `ProductDescription` · `Money` · `EmbeddingVector`
opcional · `indexedAt` opcional. Comportamiento: `register`,
`reviseCatalogData` (invalida un embedding obsoleto), `index` (registra el
evento), `remove`, `searchableText`, `isIndexed`.

**Value objects**

| Value object         | Invariante                                                                     |
| -------------------- | ------------------------------------------------------------------------------ |
| `ProductId`          | UUID, generado por el cliente.                                                 |
| `ProductName`        | No vacío, ≤ 150 caracteres.                                                    |
| `ProductDescription` | No vacío, ≤ 5000 caracteres.                                                   |
| `Money`              | Importe entero en unidades menores + divisa ISO-4217. Sin floats ni negativos. |
| `EmbeddingVector`    | Lista inmutable de floats finitos; calcula la similitud coseno.                |
| `SearchQuery`        | Texto no vacío y recortado, ≤ 500 caracteres.                                  |
| `SearchLimit`        | Entero en `[1, 50]`, por defecto `10`.                                         |
| `RelevanceScore`     | Similitud coseno en `[-1, 1]`.                                                 |
| `SearchResults`      | Lista ordenada; impone la invariante de relevancia descendente.                |

**Eventos de dominio** — `ProductWasIndexed`, `ProductWasRemoved` (disparan la
proyección y la evicción del modelo de lectura).

**Puertos** — `ProductRepository`, `EmbeddingGenerator`, `Clock` (dominio);
`SemanticProductSearch`, `ProductSearchIndex` (búsqueda).

La especificación completa —reglas de negocio, flujos de los casos de uso,
*happy/sad paths* y restricciones arquitectónicas— está en
[`ai/PLAN.md`](ai/PLAN.md).

---

## API

Especificación completa: [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1).

| Método   | Ruta                             | Propósito                                    |
| -------- | -------------------------------- | -------------------------------------------- |
| `POST`   | `/api/products`                  | Indexar (registrar o refrescar) un producto. |
| `GET`    | `/api/products/search?q=&limit=` | Buscar productos por intención.              |
| `DELETE` | `/api/products/{id}`             | Eliminar un producto del catálogo.           |
| `GET`    | `/api/health`                    | Sonda de disponibilidad (ping a PostgreSQL). |

**Indexar un producto**

```bash
curl -X POST http://localhost:8080/api/products \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "Maillot térmico de invierno",
    "description": "Camiseta térmica de manga larga, color rojo, para ciclismo en frío.",
    "price": { "amount": 5999, "currency": "EUR" }
  }'
# 201 -> {"id":"<uuid>"}
```

**Buscar**

```bash
curl 'http://localhost:8080/api/products/search?q=ropa%20roja%20para%20el%20fr%C3%ADo&limit=5'
# 200 -> {"query":"...","count":N,"results":[{"id","name","description","price","score"}, ...]}
```

**Eliminar un producto**

```bash
curl -X DELETE http://localhost:8080/api/products/<uuid>
# 204 si se elimina, 404 si el producto no existe
```

Una entrada inválida (consulta vacía, divisa incorrecta, `limit` fuera de
rango) devuelve `400` con `{"error": "..."}`. Un producto inexistente devuelve
`404`; una caída del proveedor de embeddings devuelve `502`.

---

## Levantar el entorno con Docker

Requiere Docker con Compose. Un solo comando levanta la aplicación,
PostgreSQL y Elasticsearch:

```bash
docker compose up --build
```

Al arrancar, el contenedor de la aplicación espera a PostgreSQL, ejecuta las
migraciones, crea el índice de búsqueda del backend seleccionado y siembra
un catálogo demo de 12 productos (`app:seed`, idempotente). La API queda en
**http://localhost:8080** con datos listos para consultar.

Funciona **sin API key**: el valor por defecto `EMBEDDING_PROVIDER=hashing`
usa el generador de embeddings offline y determinista. Para búsqueda semántica
real, usa OpenAI:

```bash
EMBEDDING_PROVIDER=openai OPENAI_API_KEY=sk-... docker compose up --build
```

**Cambiar el almacén de vectores.** Por defecto es Elasticsearch. Para usar
PostgreSQL + `pgvector` en su lugar, basta con una variable; nada más cambia:

```bash
SEARCH_BACKEND=pgvector docker compose up --build
```

(El servicio `database` ya usa la imagen `pgvector/pgvector`, así que la
extensión está disponible; con `SEARCH_BACKEND=pgvector` el servicio
`elasticsearch` simplemente no se usa y puede quitarse de
`docker-compose.yml`.)

Hay atajos en el `Makefile` (`make up`, `make test`, `make reindex`,
`make benchmark`, …).

---

## Ejecutar los tests

Con el stack levantado (`docker compose up --build -d`), basta con:

```bash
docker compose exec app composer test       # suite completa
docker compose exec app composer quality    # cs + phpstan + tests
```

**87 tests, 171 aserciones — en verde.** La suite tiene cuatro capas:

| Suite         | Qué cubre                                                | Infraestructura             |
| ------------- | -------------------------------------------------------- | --------------------------- |
| `unit`        | Dominio: value objects, agregado, invariantes.           | ninguna                     |
| `application` | Handlers de casos de uso, happy y sad paths.             | ninguna                     |
| `functional`  | Stack HTTP real: routing, controladores, buses, errores. | ninguna (dobles en memoria) |
| `integration` | Adaptadores reales: Doctrine, pgvector, Elasticsearch.   | PostgreSQL + Elasticsearch  |

Las tres primeras capas **no necesitan base de datos ni Elasticsearch**: el
dominio y los casos de uso se ejercitan contra dobles en memoria, y en el
entorno de test los tests funcionales también sustituyen los adaptadores de
infraestructura por esos dobles (ver `config/services_test.yaml`). La suite
`integration` **se salta a sí misma** cuando no hay base de datos o
Elasticsearch accesibles, así que `composer test` queda en verde también
fuera del contenedor.

La base de datos de tests (`app_test`) se crea automáticamente la primera vez
que arranca el servicio `database` (ver `docker/postgres-init.sh`), y
`tests/bootstrap.php` reenruta `DATABASE_URL` a `DATABASE_URL_TEST` para que
los tests de integración **nunca toquen** la base `app` que usa la
aplicación. Para correr solo la suite de integración:

```bash
docker compose exec app vendor/bin/phpunit --testsuite integration
```

Quality gate completo — **php-cs-fixer + PHPStan (nivel 6, sin errores) +
PHPUnit**, los tres deben pasar:

```bash
docker compose exec app composer quality
```

La integración continua (`.github/workflows/ci.yml`) ejecuta el quality gate
sobre PHP 8.4 más la suite de integración contra contenedores reales de
PostgreSQL y Elasticsearch, en cada push. El estado se refleja en el badge de
CI al principio de este README.

---

## Rendimiento

**Modelo de coste.** Cada operación tiene un coste fijo y predecible:

- *Indexar* — 1 generación de embedding + 1 escritura en PostgreSQL + 1
  *upsert* en el almacén de vectores. Los embeddings se calculan **una sola
  vez**, al indexar.
- *Buscar* — 1 generación de embedding para la consulta + 1 consulta de
  vecino más cercano en el almacén de vectores. El catálogo **nunca se
  recorre en PHP**.

**Por qué la búsqueda se mantiene rápida al crecer el catálogo.** El ranking
se delega a un índice de vecino más cercano aproximado (HNSW de
`dense_vector` en Elasticsearch, o un índice HNSW de pgvector): el coste crece
de forma ~logarítmica con el tamaño del catálogo, no lineal. En Elasticsearch,
`num_candidates = max(100, 10 × limit)` es la palanca explícita de
recall/latencia.

### Números medidos

`php bin/console app:benchmark` siembra el catálogo y cronometra la búsqueda
extremo a extremo a través del bus de consultas CQRS real. Ejecución: **300
productos, 300 búsquedas, top-10**, generador `hashing`, Docker local (Apple
Silicon), un solo proceso.

| Métrica (latencia de búsqueda) | pgvector | Elasticsearch |
| ------------------------------ | -------- | ------------- |
| p50                            | 11,6 ms  | 8,5 ms        |
| p95                            | 32,0 ms  | 20,4 ms       |
| p99                            | 52,4 ms  | 93,6 ms       |
| media                          | 21,4 ms  | 17,6 ms       |
| throughput                     | 47 q/s   | 57 q/s        |

Reproducirlo:

```bash
docker compose exec app php bin/console app:benchmark --products=300 --searches=300
```

**Cómo leer los números.**

- El generador `hashing` es in-process y sub-milisegundo, así que estas cifras
  aíslan la parte que controla el proyecto: el pipeline de consulta + el
  *round-trip* al almacén de vectores. Con el generador `openai`, hay que
  sumar la llamada HTTP del embedding (~100–400 ms, dependiente de red) — por
  eso los embeddings se precalculan al indexar y nunca se recalculan por
  búsqueda.
- Ambos backends están entre un dígito y decenas bajas de milisegundos a este
  tamaño de catálogo; Elasticsearch va algo por delante en p50/p95, pgvector
  tiene un p99 más ajustado. Con 300 productos la elección no se decide por
  rendimiento — es el compromiso arquitectónico que se discute en
  [`ai/DECISIONS.md`](ai/DECISIONS.md).
- La primera consulta de cada ejecución es un *outlier* de arranque en frío
  (~1,7 s: calentamiento del contenedor, opcache, primer acceso al índice).
  Cae en `max`, no en p50/p95/p99.

---

## Documentación de diseño

La carpeta [`ai/`](ai/) recoge el diseño del proyecto y el proceso de
desarrollo asistido por IA:

- [`ai/PLAN.md`](ai/PLAN.md) — especificación funcional y técnica.
- [`ai/DECISIONS.md`](ai/DECISIONS.md) — decisiones de diseño en las que se
  descartaron sugerencias genéricas del asistente.
- [`ai/prompts/`](ai/prompts/) — las conversaciones clave que guiaron la
  implementación.
- [`ai/agents/`](ai/agents/) — un agente de revisión de arquitectura a medida.
- [`CLAUDE.md`](CLAUDE.md) — las reglas que mantienen coherente el código
  generado.
