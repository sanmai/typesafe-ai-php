# TypeSafe AI PHP SDK

A PHP client for the [TypeSafe AI](https://docs.typesafe.ai) evaluation API: provide a state and a set of typed questions, get back one typed answer for each question. As easy as that.

```bash
composer require sanmai/typesafe-ai-php
```

Requires PHP 8.2 or newer.

**Something amiss?** [Open an issue](https://github.com/sanmai/typesafe-ai-php/issues/new), or, even better, send a PR!

## Overview

There are three kinds of objects you work with:

- **The client.** `TypeSafeClient` sends requests. Build it with the static `createInstance()` factory.
- **The request.** `SystemOneRequest` retains the state to evaluate and the questions about it. You build it with a fluent interface.
- **The response.** `SystemOneResult` retains one answer for each question, under the same id you gave the question.

## Usage

To get started, point an agent at this README. If you are an agent or a curious human, read on.

### Building a Client

```php
use TypeSafeAI\TypeSafeClient;

$client = TypeSafeClient::createInstance($apiKey);
```

The API key is optional: without one the client reads `TYPESAFE_API_KEY`, and throws `InvalidArgumentException` when that is not set either. `TYPESAFE_BASE_URL` overrides the API root the same way. Both names are the ones the JS and Python SDKs use.

```php
$client = TypeSafeClient::createInstance();
```

### Asking Questions

There are three question types:

- **Noul** is a yes/no question. The answer is the probability of yes, from 0 to 1.
- **Choice** picks one option from the set you define. Give each option a description, or `null` when it needs none.
- **Score** rates the state along the ordered levels you define, from the lowest to the highest.

```php
use TypeSafeAI\SystemOneRequest;

$request = SystemOneRequest::build('Help! My payouts have been failing for 3 days.')
    ->noul(
        'is_urgent',
        'Does this convey urgency?',
        true: 'Explicitly time-sensitive',
        false: 'No urgency expressed',
    )
    ->choice('department', 'Which team should handle this?', [
        'billing' => 'Payments, invoicing, refunds',
        'technical' => 'Bugs, outages, integrations',
        'sales' => null,
    ])
    ->score('frustration', 'How frustrated is the customer?', [
        'Calm',
        'Frustrated',
        'Very angry',
    ]);

$response = $client->systemOne($request);
```

The state and the instructions can be a string, or structured data such as an array or an object. Arrays and `stdClass` objects are sent as they are. Other objects are sent with their properties, private properties too; properties that are null are sent as null, and `JsonSerializable` is not used. If you need full control, convert the object to an array first.

Every description takes the same range of values: text, a JSON object, an array, or `null`. Use structure when a plain sentence leaves the boundary unclear:

```php
$request = SystemOneRequest::build($ticket)
    ->choice('department', 'Which team should handle this?', [
        'billing' => [
            'what' => 'Charges, invoices, refunds, or subscriptions',
            'not_for' => 'Order tracking or account access',
            'examples' => ['I was charged twice', 'Where is my refund?'],
        ],
        'sales' => null,
    ]);
```

`instructions` is optional, so a question can lean on its criteria alone. The API needs at least one of the two: a Noul with neither is rejected with `400 Bad Request`.

The request uses the `jev-latest` model by default; to use a different model, give its name as the second argument to `build()`. You can also use the constructor; `build()` exists only for method chaining before PHP 8.4:

```php
use TypeSafeAI\Question\Noul;
use TypeSafeAI\Question\NoulCriteria;

$request = new SystemOneRequest($state, questions: [
    'is_urgent' => new Noul(
        'Does this convey urgency?',
        new NoulCriteria(false: 'No urgency expressed'),
    ),
]);
```

### Reading Answers

Each answer type has its own accessor, so static analysis knows which fields are available:

```php
$urgent = $response->noul('is_urgent');
$urgent->noul;                   // 0.95

$department = $response->choice('department');
$department->choice;             // 'billing'
$department->probabilities;      // ['sales' => 0.0, 'technical' => 0.11, 'billing' => 0.89]
$department->confidence;         // 0.82

$frustration = $response->score('frustration');
$frustration->score;             // 1.04
$frustration->legend;            // [0 => 'Calm', 1 => 'Frustrated', 2 => 'Very angry']
$frustration->probabilities;     // [0 => 0.0, 1 => 0.96, 2 => 0.04]
$frustration->confidence;        // 0.94

$response->model;                // 'jev-N.NN', the actual model version
$response->usage->input_tokens;  // 414
```

Each answer also carries its `type`, which is useful when you walk `$response->answers` instead of asking for an id you know:

```php
$urgent->type;                   // 'noul'
```

The levels in `legend` come back as they were sent, so they are text when you sent text, and structured JSON when you sent that.

The score is the mean of the level numbers, weighted by their probabilities: here 0.96 × 1 + 0.04 × 2 = 1.04. Thus it can land between levels. The probabilities come in the order that the API gives, which can be different from the order of your options or levels.

An accessor throws `UnexpectedValueException` if there is no answer with that id, or if the answer has a different type. All answers are also available in `$response->answers`, keyed by question id.

### Listing Models

`models()` returns the models available to your account:

```php
foreach ($client->models()->models as $model) {
    echo "{$model->name}: {$model->description} ({$model->release_date})\n";
}
```

### Custom Question Types

`SystemOneRequest::ask()` adds any object that implements the `TypeSafeAI\Question\Question` marker interface. The `noul()`, `choice()`, and `score()` methods are shortcuts for `ask()` with the bundled `Noul`, `Choice`, and `Score` classes.

Questions are plain data objects. The client serializes their properties to JSON, null values too. To add a question type, write a class with a `type` property and the fields that the API expects for that type. If the API does not accept null for an optional field, see how `NoulCriteria` leaves out the descriptions that are not set. The SDK can send a custom question, but it cannot read the answer: an answer type that the SDK does not know causes `systemOne()` to throw `JMS\Serializer\Exception\LogicException`, and the other answers in the response are lost too.

## Examples

The [examples](examples/) directory has scripts that you can run. Each one reads the API key from the `TYPESAFE_API_KEY` environment variable:

```bash
TYPESAFE_API_KEY=your-api-key php examples/urgency.php
```

- [urgency.php](examples/urgency.php): a yes/no question.
- [routing.php](examples/routing.php): a choice between teams, with the probability of each option.
- [frustration.php](examples/frustration.php): a score along ordered levels.
- [chat-log.php](examples/chat-log.php): questions of all three types about a chat log, in one request.
- [errors.php](examples/errors.php): an invalid request, and the validation error that the API returns.

## Errors and Retries

The client retries `408 Request Timeout`, `429 Too Many Requests`, every `5xx` response, and connection timeouts, twice at most. Other errors throw Guzzle exceptions: a `ClientException` for `401 Unauthorized` (check your API key), for `400 Bad Request` (a question that the API cannot use, such as a choice without options), and for `422 Unprocessable Entity` (the response body identifies the field that failed validation).

```php
use GuzzleHttp\Exception\ClientException;

try {
    $response = $client->systemOne($request);
} catch (ClientException $e) {
    $error = $e->getResponse();
    echo "Request failed (HTTP {$error->getStatusCode()}): {$error->getBody()}\n";
}
```

## Client Configuration

Requests time out after 10 seconds. Raise `timeout` through `$clientOptions` when you ask many questions at once.

`createInstance()` takes optional `$extraHeaders`, `$retryOptions`, and `$clientOptions` arrays after the API key. Use them to send extra headers (a custom User-Agent, for example), to tune the bundled [retry middleware](https://github.com/caseyamcl/guzzle_retry_middleware), or to override defaults such as `base_uri`, `timeout`, or `connect_timeout`. Do not give `headers` or `handler` in `$clientOptions`: they replace the defaults, so the client loses the `Authorization` header, or the retries and the logger. Use `$extraHeaders` for headers.

## Debug Logging

`TypeSafeClient` accepts any PSR-3 logger through `setLogger()`. The logger records full request and response bodies, which helps while you experiment. The default template, `TypeSafeClient::LOG_TEMPLATE`, leaves out the headers, so your API key does not get into the logs. If you give your own template as the second argument, do not use `{request}` or `{req_headers}`, because they include the `Authorization` header:

```php
$client->setLogger($psrLogger);
```

## Development

To run all tests:

```bash
make -j -k
```
