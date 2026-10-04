<?php

namespace Tests\Unit;

use App\Support\Refusal;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Found building S3: QueryException and Symfony's HttpException both extend
 * RuntimeException, so the billing controllers' `catch (RuntimeException)` handed a
 * customer raw SQL as an error "reason", and answered an authorization 404 with an
 * empty 422. Only genuine refusals may become messages.
 */
class RefusalTest extends TestCase
{
    public function test_a_refusal_becomes_its_message(): void
    {
        $this->assertSame('Pay the open renewal first.', Refusal::message(new RuntimeException('Pay the open renewal first.')));
    }

    public function test_a_database_failure_is_rethrown_never_shown(): void
    {
        $e = new QueryException('mysql', 'insert into `subscription_orders` …', [], new PDOException('SQLSTATE[23000]'));

        $this->expectException(QueryException::class);
        Refusal::message($e);
    }

    public function test_an_http_abort_is_rethrown_so_a_404_stays_a_404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        Refusal::message(new NotFoundHttpException);
    }
}
