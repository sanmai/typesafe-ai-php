# AI Agent Guidelines

This is a community-maintained PHP client for the TypeSafe AI evaluation API.

It is designed to be type-safe and easy to use: requests are built with a fluent interface, and each answer type has a typed accessor.

- **PHP Version:** 8.2 or newer.
- **API reference:** https://docs.typesafe.ai (one endpoint: `POST /v1/systemone`).
- **Core Architecture:**
    - **Client:** `TypeSafeClient` sends requests and deserializes responses.
    - **Request:** `EvaluationRequest` holds the state and a map of `Question` DTOs (`Noul`, `Choice`, `Score`). The client serializes it with JMS.
    - **Response/DTOs:** `EvaluationResponse` holds a map of `Answer` DTOs. JMS picks the subclass from the `type` field.

End-user documentation:

@README.md

## Project Navigation

**Where to look:**
- **Core Logic:** @src/TypeSafeClient.php (the main entry point for all API calls).
- **Request:** `src/EvaluationRequest.php` and `src/Question/`.
- **Data Models:** `src/EvaluationResponse.php` and `src/DTO/` (all response objects).
- **Tests:** `tests/`, with response fixtures in `tests/data/`.

## Coding Standards

- **Type Hinting:** Use precise type hints for parameters and return types. Use generics (`@template`) where appropriate.
- **DTOs:** Data Transfer Objects are simple classes with public properties.
- **Little logic in DTOs:** Requests and responses are mostly pure data: public properties. Put serialization rules in JMS attributes where possible. When a rule applies to one DTO only, a small serialization hook on that DTO (see `NoulCriteria::descriptions()`) is better than a global strategy in the serialization context of the client. The request builder methods only pass their arguments through.
- **No validation:** Question types do not validate their contents; the API does, so the SDK keeps working when the API relaxes a rule.
- **Naming:** Follow PER-CS coding standards (extended PSR-12). Run `make cs` to validate.

## Implementation Details

- **JSON maps**: The API uses maps keyed by ids and options that you choose. Declare them with a key type, such as `#[Type('array<string, string>')]`: JMS then writes a JSON object, also when the map is empty or has keys such as `"0"`. Declare lists as `array<string>`; JMS re-indexes them.
- **Nulls**: The client serializes with `serializeNull` on, so all null values are sent: in arrays (a choice option without a description) and in the user state. If a DTO has optional fields that the API must not get as null, the DTO leaves them out itself: exclude the properties and add an inline virtual property that returns only the values that are set (see `NoulCriteria::descriptions()`). To leave out a nested object when it gives no values, add `#[SkipWhenEmpty]` (see `Noul::$criteria`).
- **Question type field**: Each question class has a `public string $type` property with a default value. There is no discriminator on requests, so a custom question type does not need a change in the SDK.
- **Answer types**: `DTO\Answer` has a JMS `#[Discriminator]` on the `type` field. To add an answer type, add a subclass, a map entry, and an accessor on `EvaluationResponse`.
- **JMS attributes**: Use PHP attributes such as `#[Type(...)]` for JMS serializer metadata. Keep PHPDoc like `@var` where it provides static-analysis detail.
- **Serializer property names**: The JSON serializer uses JMS' `IdenticalPropertyNamingStrategy`, so DTO property names must match API field names unless a `#[SerializedName(...)]` override is added.
- **Retries**: `429` and `529` responses and connection timeouts are retried by `GuzzleRetryMiddleware`. Other HTTP errors throw Guzzle exceptions.

## Development Workflow

1. **Code Standardization**: Always run `make cs` before submitting changes for review. This ensures style compliance (PER-CS), applies modern PHP standards, removes unused imports, and maintains project-wide structural consistency.
2. **Full Verification**: Run `make -j -k` to execute the complete validation pipeline in parallel and identify all failures at once. This typically includes:
    - Coding style and linting.
    - Static analysis.
    - Unit and functional tests, with 100% code coverage.
    - Mutation testing.
    - Package and configuration validation.
    *Refer to the output of `make -j -k` for the exact tools and current configurations.*
3. **Testing Requirement**: Every new feature or bug fix must be accompanied by a corresponding test in the `tests/` directory.
    - To run a single test file while iterating: `vendor/bin/phpunit tests/SpecificTest.php`.
    - Data providers run before coverage is collected. If a provider builds the object under test, yield a closure and call it in the test.
4. **Mocking**: Client tests use the real `createInstance()` and replace the handler of its stack with a Guzzle `MockHandler`; see `tests/TypeSafeClientTest.php`.
5. **Fixtures**: Response JSON goes in `tests/data/`. `SerializationTest` checks that each file deserializes and serializes back to the same JSON.

The build system uses `chronic` to suppress output for successful commands; if a command produces no output, it has succeeded.

## Documentation Style

- **No Hard-Wrapped Lines:** Write each paragraph as a single long line in Markdown files. Let the editor handle soft-wrapping.
- **Clarity:** Keep documentation concise and focused on usage examples.
