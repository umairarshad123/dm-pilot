<?php

namespace App\Exceptions;

/** A failed OpenAI request. Never contains the API key or prompt text. */
class OpenAIException extends AiProviderException {}
