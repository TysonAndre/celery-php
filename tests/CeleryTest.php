<?php
// phpunit CeleryTest unittest.php
/*
 * LICENSE: 2-clause BSD
 *
 * Copyright (c) 2012, GDR!
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * The views and conclusions contained in the software and documentation are those
 * of the authors and should not be interpreted as representing official policies,
 * either expressed or implied, of the FreeBSD Project.
 *
 * http://massivescale.net/
 * http://gdr.geekhood.net/
 * gdr@go2.pl
 */

/*
 * INSTALL:
sudo apt-get remove phpunit
composer global require 'phpunit/phpunit'

 * RUN:
 phpunit
#or:
 phpunit CeleryAMQPLibTest unittest/CeleryAMQPLibTest.php
 */

namespace Celery\Tests;

abstract class CeleryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Celery\CeleryException
     */
    public function testArgsValidation()
    {
        $c = $this->get_c();

        $c->PostTask('task.test', 'arg');
    }

    public function testCorrectOperation()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add', [2, 2]);

        for ($i = 0; $i < 10; $i++) {
            if ($result->isReady()) {
                break;
            } else {
                sleep(1);
            }
        }
        $this->assertTrue($result->isReady());

        $this->assertTrue($result->isSuccess());
        $this->assertSame(4, $result->getResult());
    }

    public function testCorrectOperationString()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add', ['foo', 'bar']);

        for ($i = 0; $i < 10; $i++) {
            if ($result->isReady()) {
                break;
            } else {
                sleep(1);
            }
        }
        $this->assertTrue($result->isReady());

        $this->assertTrue($result->isSuccess());
        $this->assertSame('foobar', $result->getResult());
    }

    public function testCorrectOperationStringLong()
    {
        $c = $this->get_c();

        $printable = ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~\',===+++///';

        $result = $c->PostTask('tasks.add', [$printable, $printable]);

        for ($i = 0; $i < 10; $i++) {
            if ($result->isReady()) {
                break;
            } else {
                sleep(1);
            }
        }
        $this->assertTrue($result->isReady());

        $this->assertTrue($result->isSuccess());
        $this->assertSame($printable . $printable, $result->getResult());
    }

    public function testCorrectOperationStringVeryLong()
    {
        $c = $this->get_c();

        $printable = json_encode(['data' => base64_encode(random_bytes(1000001))]);

        $result = $c->PostTask('tasks.add', [$printable, 'suffix']);

        for ($i = 0; $i < 10; $i++) {
            if ($result->isReady()) {
                break;
            } else {
                sleep(1);
            }
        }
        $this->assertTrue($result->isReady());

        $this->assertTrue($result->isSuccess());
        $this->assertSame($printable . 'suffix', $result->getResult());
    }

    public function testIdentityOperationVeryLong()
    {
        $c = $this->get_c();

        $printable = [
            'aid' => 11111334224242,
            'bid' => '11111334224242_12341451',
            'data' => base64_encode(random_bytes(10000))
        ];

        $result = $c->PostTask('tasks.identity', [$printable]);

        for ($i = 0; $i < 10; $i++) {
            if ($result->isReady()) {
                break;
            } else {
                sleep(1);
            }
        }
        $this->assertTrue($result->isReady());

        $this->assertTrue($result->isSuccess());
        $data = (array)$result->getResult();
        ksort($data);
        $this->assertSame($printable, $data);
    }


    public function testFailingOperation()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.fail', []);

        for ($i = 0; $i < 20; $i++) {
            if ($result->isReady()) {
                break;
            } else {
                sleep(1);
            }
        }
        $this->assertTrue($result->isReady());

        $this->assertFalse($result->isSuccess());
        $this->assertGreaterThan(1, strlen($result->getTraceback()));
    }

    /**
     * @expectedException \Celery\CeleryException
     */
    public function testPrematureGet()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.delayed', []);
        $result->isSuccess();
    }

    /**
     * @expectedException \Celery\CeleryException
     */
    public function testPrematureGetTraceback()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.delayed', []);
        $result->getTraceback();
    }

    /**
     * @expectedException \Celery\CeleryException
     */
    public function testPrematureGetResult()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.delayed', []);
        $result->getResult();
    }

    public function testFailed()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.fail', []);
        $result->get();
        $this->assertTrue($result->failed());
    }

    /*
     * Test Python API
     * Based on http://www.celeryproject.org/tutorials/first-steps-with-celery/
     */
    public function testGet()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add_delayed', [4, 4]);
        $this->assertFalse($result->ready());
        $this->assertNull($result->result);
        $rv = $result->get();
        $this->assertSame(8, $rv);
        $this->assertSame(8, $result->result);
        $this->assertTrue($result->successful());
    }

    public function testKwargs()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add_delayed', ['x' => 4, 'y' => 4]);
        $this->assertFalse($result->ready());
        $this->assertNull($result->result);
        $rv = $result->get();
        $this->assertSame(8, $rv);
        $this->assertSame(8, $result->result);
        $this->assertTrue($result->successful());
    }

    /**
     * @expectedException \Celery\CeleryTimeoutException
     */
    public function testzzzzGetTimeLimit()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.delayed', []);
        $result->get(1, true, 0.1);
    }

    public function testStateProperty()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.delayed', []);
        $this->assertSame($result->state, 'PENDING');
        $result->get();
        $this->assertSame($result->state, 'SUCCESS');
    }

    /* NO-OP functions should not fail */
    public function testForget()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add', [2, 2]);
        $result->forget();
        $result->revoke();
    }

    public function testWait()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add', [4, 4]);
        $rv = $result->wait();
        $this->assertSame(8, $rv);
        $this->assertSame(8, $result->result);
        $this->assertTrue($result->successful());
    }

    public function testSerialization()
    {
        $c = $this->get_c();

        $result_tmp = $c->PostTask('tasks.add_delayed', [4, 4]);
        $result_serialized = serialize($result_tmp);
        $result = unserialize($result_serialized);
        $rv = $result->get();
        $this->assertSame(8, $rv);
        $this->assertSame(8, $result->result);
        $this->assertTrue($result->successful());
    }

    public function testGetAsyncResult()
    {
        $c = $this->get_c();

        $result_tmp = $c->PostTask('tasks.add', [427552, 1]);
        $id = $result_tmp->getId();
        sleep(1);
        $result = $c->getAsyncResultMessage('tasks.add', $id, null, false);
        $this->assertTrue(strpos($result['body'], '427553') >= 0);
        $result = $c->getAsyncResultMessage('tasks.add', $id, null, false);
        $this->assertTrue(strpos($result['body'], '427553') >= 0);
        $result = $c->getAsyncResultMessage('tasks.add', $id, null, true);
        $this->assertTrue(strpos($result['body'], '427553') >= 0);
    }

    public function testReturnedArray()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.get_fibonacci', []);
        $rv = $result->wait();
        $this->assertSame(1, $rv[0]);
        $this->assertSame(34, $rv[8]);
    }
}
