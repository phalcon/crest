<?php

declare(strict_types=1);

namespace Crest\Action\Privacy;

use Phalcon\ADR\Payload\Payload;
use Phalcon\ADR\Responder\ViewResponder;
use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;

final class GetPrivacy implements Action
{
    public function __construct(
        private readonly ViewResponder $responder,
    ) {
    }

    public function __invoke(AttributeRequest $request): ResponseInterface
    {
        return ($this->responder->withTemplate('privacy/index'))(
            $request,
            new Response(),
            Payload::success()
        );
    }
}
