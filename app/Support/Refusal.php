<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use PDOException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Turns a billing service's refusal into a message for the person who asked — and
 * refuses to do that for anything that is not a refusal.
 *
 * The billing services throw RuntimeException with a sentence a human can act on
 * ("This customer already has a live renewal link…"), and the controllers catch
 * RuntimeException to show it. The trap, found building S3: two things that are
 * NOT refusals also extend RuntimeException —
 *
 *  · QueryException (via PDOException). A genuine database failure was caught and
 *    its raw SQL handed to the user as the "reason" — on the owner's page, to a
 *    CUSTOMER — while the real error was never reported.
 *  · Symfony's HttpException, which abort(404) throws. An authorization refusal was
 *    swallowed and answered 422 with an empty message.
 *
 * So: refusals become messages; infrastructure failures and HTTP aborts are
 * rethrown, to be logged and rendered by the exception handler like any other.
 */
final class Refusal
{
    public static function message(RuntimeException $e): string
    {
        if ($e instanceof QueryException || $e instanceof PDOException || $e instanceof HttpExceptionInterface) {
            throw $e;
        }

        return $e->getMessage();
    }
}
