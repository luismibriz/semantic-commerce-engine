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

- [Arquitectura](#arquitectura)
- [Modelado del dominio](#modelado-del-dominio)
- [API / OpenAPI](#api)
- [Levantar el entorno con Docker](#levantar-el-entorno-con-docker)
- [Ejecutar los tests](#ejecutar-los-tests)
- [Rendimiento](#rendimiento)
- [Documentación de diseño](#documentación-de-diseño)

---

## Arquitectura

dependencias apuntan **hacia dentro**; el dominio no conoce el exterior.

```
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
migraciones de base de datos y crea el índice de búsqueda del backend
seleccionado. La API queda en **http://localhost:8080**.

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

```bash
composer install
composer test          # la suite completa
```

O dentro del contenedor: `docker compose exec app vendor/bin/phpunit`.

**84 tests, 156 aserciones — en verde.** La suite tiene cuatro capas:

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
Elasticsearch accesibles, así que `composer test` queda en verde en cualquier
sitio.

Para ejecutar la suite de integración en local, levanta el stack y crea una
vez la base de datos de test:

```bash
docker compose up -d
docker compose exec database psql -U app -c 'CREATE DATABASE app_test'
vendor/bin/phpunit --testsuite integration
```

Quality gate completo — **php-cs-fixer + PHPStan (nivel 6, sin errores) +
PHPUnit**, los tres deben pasar:

```bash
composer quality
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
