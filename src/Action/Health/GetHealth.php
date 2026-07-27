<?php

declare(strict_types=1);

namespace Crest\Action\Health;

use Phalcon\ADR\Payload\Payload;
use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\ADR\Responder\Responder;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Http\ResponseInterface;

final class GetHealth implements Action
{
    public function __construct(
        private readonly Responder $responder,
        private readonly ResponseInterface $response,
    ) {
    }

    public function __invoke(AttributeRequest $request): ResponseInterface
    {
        $payload = Payload::success([]);

        return ($this->responder)($request, $this->response, $payload);
    }
}
