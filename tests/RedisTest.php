<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/9/24 0024
 * Time: 16:16
 */

namespace Test;

use EasySwoole\Redis\Client;
use EasySwoole\Redis\Config\RedisConfig;
use EasySwoole\Redis\Redis;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

class RedisTest extends TestCase
{
    /**
     * @var $redis Redis
     */
    protected $redis;
    /**
     * @var $redisPHPSerialize Redis
     */
    protected $redisPHPSerialize;

    protected function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->redis = new Redis(new RedisConfig([
            'host'=>REDIS_HOST,
            'port'=>REDIS_PORT,
            'auth'=>REDIS_AUTH
        ]));
        $this->redisPHPSerialize = new Redis(new RedisConfig([
            'host'=>REDIS_HOST,
            'port'=>REDIS_PORT,
            'auth'=>REDIS_AUTH,
            'serialize'=>RedisConfig::SERIALIZE_PHP
        ]));
        $this->redis->connect();
        $this->redis->auth('easyswoole');
        $this->redisPHPSerialize->connect();
        $this->redisPHPSerialize->auth('easyswoole');
    }

    function testConnect()
    {
        $this->assertTrue($this->redis->connect());
    }

    public function testAuth()
    {
        if(!empty(REDIS_AUTH)){
            $this->assertTrue($this->redis->auth(REDIS_AUTH));
        }
        $this->assertTrue(true);
    }

    /**
     * key值操作测试
     * testKey
     * @author Tioncico
     * Time: 10:02
     */
    function testKey()
    {
        $redis = $this->redis;
        $key = 'test123213Key';
        $redis->select(0);
        $redis->set($key, 123);
        $data = $redis->dump($key);
        $this->assertTrue(!!$data);

        $data = $redis->dump($key . 'x');
        $this->assertNull($data);

        $data = $this->redis->exists($key);
        $this->assertEquals(1, $data);

        $data = $this->redis->expire($key, 1);
        $this->assertEquals(1, $data);
        Coroutine::sleep(2);
        $this->assertEquals(0, $this->redis->exists($key));

        $redis->expireAt($key, 1 * 100);
        Coroutine::sleep(0.1);
        $this->assertEquals(0, $this->redis->exists($key));

        $redis->set($key, 123);
        $data = $redis->keys("{$key}*");
        $this->assertEquals($key, $data[0]);

        $redis->select(1);
        $redis->del($key);
        $redis->select(0);
        $data = $redis->move($key, 1);
        $this->assertEquals(1, $data);
        $data = $redis->exists($key);
        $this->assertEquals(0, $data);
        $redis->select(0);

        $redis->set($key, 123);
        $data = $redis->expire($key, 1);
        $this->assertEquals(1, $data);
        $data = $redis->persist($key);
        $this->assertEquals(1, $data);

        $redis->expire($key, 1);
        $data = $redis->pTTL($key);
        $this->assertLessThanOrEqual($data, 1000);

        $data = $redis->ttl($key);
        $this->assertLessThanOrEqual($data, 1);

        $data = $redis->randomKey();
        $this->assertTrue(!!$data);
        $data = $redis->rename($key, $key . 'new');
        $this->assertTrue($data);
        $this->assertEquals(1, $redis->expire($key . 'new'));
        $this->assertEquals(0, $redis->expire($key));

        $data = $redis->renameNx($key, $key . 'new');
        $this->assertEquals(0, $data);
        $redis->renameNx($key . 'new', $key);
        $data = $redis->renameNx($key, $key . 'new');
        $this->assertEquals(1, $data);
        $data = $redis->type($key);
        $this->assertEquals('none', $data);
        $data = $redis->type($key . 'new');
        $this->assertEquals('string', $data);
    }

    /**
     * 字符串单元测试
     * testString
     * @author tioncico
     * Time: 下午9:41
     */
    function testString()
    {
        $redis = $this->redis;
        $key = 'test';
        $value = 1;
        $data = $redis->del($key);
        $this->assertNotFalse($data);
        $data = $redis->set($key, $value);
        $this->assertTrue($data);

        $data = $redis->get($key);
        $this->assertEquals($data, $value);

        $data = $redis->exists($key);
        $this->assertEquals(1, $data);

        $data = $redis->set($key, $value);
        $this->assertTrue($data);
        $value += 1;
        $data = $redis->incr($key);
        $this->assertEquals($value, $data);

        $value += 10;
        $data = $redis->incrBy($key, 10);
        $this->assertEquals($value, $data);

        $value -= 1;
        $data = $redis->decr($key);
        $this->assertEquals($value, $data);

        $value -= 10;
        $data = $redis->decrBy($key, 10);
        $this->assertEquals($value, $data);

        $key = 'stringTest';
        $value = 'tioncico';
        $redis->set($key, $value);

        $data = $redis->getRange($key, 1, 2);
        $this->assertEquals('io', $data);

        $data = $redis->getSet($key, $value . 'a');
        $this->assertEquals($data, $value);
        $redis->set($key, $value);

        $bitKey = 'testBit';
        $bitValue = 10000;
        $redis->set($bitKey, $bitValue);
        $data = $redis->setBit($bitKey, 1, 0);
        $this->assertEquals(0, $data);
        $data = $redis->getBit($key, 1);
        $this->assertEquals(1, $data);


        $field = [
            'stringField1',
            'stringField2',
            'stringField3',
            'stringField4',
            'stringField5',
        ];
        $value = [
            1,
            2,
            3,
            4,
            5,
        ];
        $data = $redis->mSet([
            "{$field[0]}" => $value[0],
            "{$field[1]}" => $value[1],
            "{$field[2]}" => $value[2],
            "{$field[3]}" => $value[3],
            "{$field[4]}" => $value[4],
        ]);
        $this->assertTrue($data);
        $data = $redis->mGet($field[3], $field[2], $field[1]);
        $this->assertEquals([$value[3], $value[2], $value[1]], $data);


        $data = $redis->setEx($key, 1, $value[0] . $value[0]);
        $this->assertTrue($data);
        $this->assertEquals($value[0] . $value[0], $redis->get($key));

        $data = $redis->pSetEx($key, 1, $value[0]);
        $this->assertTrue($data);
        $this->assertEquals($value[0], $redis->get($key));


        $redis->del($key);
        $data = $redis->setNx($key, 1);
        $this->assertEquals(1, $data);


        $redis->del($field[0]);
        $data = $redis->mSetNx([
            "{$field[0]}" => $value[0],
            "{$field[1]}" => $value[1],
        ]);
        $this->assertEquals(0, $data);
        $this->assertEquals($value[1], $redis->get($field[1]));
        $redis->del($field[1]);
        $data = $redis->mSetNx([
            "{$field[0]}" => $value[0] + 1,
            "{$field[1]}" => $value[1] + 1,
        ]);
        $this->assertEquals(1, $data);
        $this->assertEquals($value[0] + 1, $redis->get($field[0]));


        $data = $redis->setRange($field[0], 1, 1);
        $this->assertEquals(2, $data);
        $this->assertEquals('2' . $value[0], $redis->get($field[0]));

        $data = $redis->strLen($field[0]);
        $this->assertEquals(2, $data);

        $redis->set($key, 1);
        $data = $redis->incrByFloat($key, 0.1);
        $this->assertEquals(1.1, $data);
        $data = $redis->appEnd($field[0], '1');
        $this->assertEquals($redis->strLen($field[0]), $data);
        $this->assertEquals('2' . $value[0] . '1', $redis->get($field[0]));
    }

    /**
     * 序列化字符串单元测试
     * testString
     * @author tioncico
     * Time: 下午9:41
     */
    function testStringSerialize()
    {
        $redis = $this->redisPHPSerialize;
        $key = 'test';
        $value = 1;
        $data = $redis->del($key);
        $this->assertNotFalse($data);
        $data = $redis->set($key, $value);
        $this->assertTrue($data);

        $data = $redis->get($key);
        $this->assertEquals($data, $value);

        $data = $redis->exists($key);
        $this->assertEquals(1, $data);


        $key = 'stringTest';
        $value = 'tioncico';
        $redis->set($key, $value);

        $data = $redis->getSet($key, $value . 'a');
        $this->assertEquals($data, $value);
        $redis->set($key, $value);


        $field = [
            'stringField1',
            'stringField2',
            'stringField3',
            'stringField4',
            'stringField5',
        ];
        $value = [
            1,
            2,
            3,
            4,
            5,
        ];
        $data = $redis->mSet([
            "{$field[0]}" => $value[0],
            "{$field[1]}" => $value[1],
            "{$field[2]}" => $value[2],
            "{$field[3]}" => $value[3],
            "{$field[4]}" => $value[4],
        ]);
        $this->assertTrue($data);
        $data = $redis->mGet($field[3], $field[2], $field[1]);
        $this->assertEquals([$value[3], $value[2], $value[1]], $data);


        $data = $redis->setEx($key, 1, $value[0] . $value[0]);
        $this->assertTrue($data);
        $this->assertEquals($value[0] . $value[0], $redis->get($key));

        $data = $redis->pSetEx($key, 1, $value[0]);
        $this->assertTrue($data);
        $this->assertEquals($value[0], $redis->get($key));


        $redis->del($key);
        $data = $redis->setNx($key, 1);
        $this->assertEquals(1, $data);


        $redis->del($field[0]);
        $data = $redis->mSetNx([
            "{$field[0]}" => $value[0],
            "{$field[1]}" => $value[1],
        ]);
        $this->assertEquals(0, $data);
        $this->assertEquals($value[1], $redis->get($field[1]));
        $redis->del($field[1]);
        $data = $redis->mSetNx([
            "{$field[0]}" => $value[0] + 1,
            "{$field[1]}" => $value[1] + 1,
        ]);
        $this->assertEquals(1, $data);
        $this->assertEquals($value[0] + 1, $redis->get($field[0]));
    }

    /**
     * testHash
     * @author Tioncico
     * Time: 11:54
     */
    function testHash()
    {
        $key = 'hKey';
        $field = [
            'hField1',
            'hField2',
            'hField3',
            'hField4',
            'hField5',
        ];
        $value = [
            1,
            2,
            3,
            4,
            5,
        ];

        $redis = $this->redis;
        $redis->del($key);
        $data = $redis->hSet($key, $field[0], $value[0]);
        $this->assertNotFalse($data);

        $data = $redis->hGet($key, $field[0]);
        $this->assertEquals($data, $value[0]);

        $data = $redis->hExists($key, $field[0]);
        $this->assertEquals(1, $data);

        $data = $redis->hDel($key, $field[0]);
        $this->assertEquals(1, $data, $redis->getErrorMsg());

        $data = $redis->hExists($key, $field[0]);
        $this->assertEquals(0, $data);

        $data = $redis->hMSet($key, [
            "{$field[0]}" => $value[0],
            "{$field[1]}" => $value[1],
            "{$field[2]}" => $value[2],
            "{$field[3]}" => $value[3],
            "{$field[4]}" => $value[4],
        ]);
        $this->assertTrue($data);
        $data = $redis->hValS($key);
        sort($data);
        $this->assertEquals($value, $data);

        $data = $redis->hGetAll($key);
        $keyTmp = array_keys($data);
        sort($keyTmp);
        $this->assertEquals($field, $keyTmp);
        $valueTmp = array_values($data);
        sort($valueTmp);
        $this->assertEquals($value, $valueTmp);
        $this->assertEquals($value, [
            $data[$field[0]],
            $data[$field[1]],
            $data[$field[2]],
            $data[$field[3]],
            $data[$field[4]],
        ]);

        $data = $redis->hKeys($key);
        sort($data);
        $this->assertEquals($field, $data);

        $data = $redis->hLen($key);
        $this->assertEquals(count($field), $data);

        $data = $redis->hMGet($key, $field[0], $field[1], $field[2]);
        $this->assertEquals([1, 2, 3], $data);

        $data = $redis->hIncrBy($key, $field[4], 1);
        $this->assertEquals($value[4] + 1, $data);

        $data = $redis->hIncrByFloat($key, $field[1], 1.1);
        $this->assertEquals($value[1] + 1.1, $data);

        $data = $redis->hSetNx($key, $field[0], 1);
        $this->assertEquals(0, $data);

        $data = $redis->hSetNx($key, $field[0] . 'a', 1);
        $this->assertEquals(1, $data);
        $this->assertEquals(1, $redis->hGet($key, $field[0] . 'a'));

//        $data = $redis->hScan($key,1,'',100);
//        var_dump($data);
//        var_dump($redis->getErrorMsg());;
    }

    /**
     * testHash序列化测试
     * @author Tioncico
     * Time: 11:54
     */
    function testHashSerialize()
    {
        $key = 'hKey';
        $field = [
            'hField1',
            'hField2',
            'hField3',
            'hField4',
            'hField5',
        ];
        $value = [
            1,
            2,
            3,
            4,
            5,
        ];

        $redis = $this->redisPHPSerialize;
        $redis->del($key);
        $data = $redis->hSet($key, $field[0], $value[0]);
        $this->assertNotFalse($data);

        $data = $redis->hGet($key, $field[0]);
        $this->assertEquals($data, $value[0]);

        $data = $redis->hExists($key, $field[0]);
        $this->assertEquals(1, $data);

        $data = $redis->hDel($key, $field[0]);
        $this->assertEquals(1, $data, $redis->getErrorMsg());

        $data = $redis->hExists($key, $field[0]);
        $this->assertEquals(0, $data);

        $data = $redis->hMSet($key, [
            "{$field[0]}" => $value[0],
            "{$field[1]}" => $value[1],
            "{$field[2]}" => $value[2],
            "{$field[3]}" => $value[3],
            "{$field[4]}" => $value[4],
        ]);
        $this->assertTrue($data);
        $data = $redis->hValS($key);
        sort($data);
        $this->assertEquals($value, $data);

        $data = $redis->hGetAll($key);
        $keyTmp = array_keys($data);
        sort($keyTmp);
        $this->assertEquals($field, $keyTmp);
        $valueTmp = array_values($data);
        sort($valueTmp);
        $this->assertEquals($value, $valueTmp);
        $this->assertEquals($value, [
            $data[$field[0]],
            $data[$field[1]],
            $data[$field[2]],
            $data[$field[3]],
            $data[$field[4]],
        ]);

        $data = $redis->hKeys($key);
        sort($data);
        $this->assertEquals($field, $data);

        $data = $redis->hLen($key);
        $this->assertEquals(count($field), $data);

        $data = $redis->hMGet($key, $field[0], $field[1], $field[2]);
        $this->assertEquals([1, 2, 3], $data);

        $data = $redis->hSetNx($key, $field[0], 1);
        $this->assertEquals(0, $data);

        $data = $redis->hSetNx($key, $field[0] . 'a', 1);
        $this->assertEquals(1, $data);
        $this->assertEquals(1, $redis->hGet($key, $field[0] . 'a'));

//        $data = $redis->hScan($key,1,'',100);
//        var_dump($data);
//        var_dump($redis->getErrorMsg());;
    }

    /**
     * testList
     * @author tioncico
     * Time: 下午8:17
     */
    function testList()
    {
        $redis = $this->redisPHPSerialize;
        $key = [
            'listKey1',
            'listKey2',
            'listKey3',
        ];
        $value = [
            'a', 'b', 'c', 'd'
        ];

        $redis->del($key[0]);
        $data = $redis->lPush($key[0], $value[0], $value[1]);
        $this->assertEquals(2, $data);

        $data = $redis->bLPop($key[0], 1);
        $this->assertTrue(!!$data);

        $data = $redis->bRPop($key[0], 1);
        $this->assertTrue(!!$data);

        $redis->del($key[0]);
        $redis->lPush($key[0], $value[0], $value[1]);
        $data = $redis->bRPopLPush($key[0], $key[1], 1);
        $this->assertEquals($value[0], $data);

        $redis->del($key[0]);
        $redis->lPush($key[0], $value[0], $value[1]);
        $data = $redis->rPopLPush($key[0], $key[1]);
        $this->assertEquals($value[0], $data);

        $redis->del($key[0]);
        $redis->lPush($key[0], $value[0], $value[1]);
        $data = $redis->lIndex($key[0], 1);
        $this->assertEquals($value[0], $data);
        $data = $redis->lLen($key[0]);
        $this->assertEquals(2, $data);

        $data = $redis->lInsert($key[0], true, 'b', 'c');
        $this->assertEquals($redis->lLen($key[0]), $data);
        $data = $redis->lInsert($key[0], true, 'd', 'c');
        $this->assertEquals(-1, $data);


        $redis->del($key[1]);
        $data = $redis->rPush($key[1], $value[0], $value[2], $value[1]);
        $this->assertEquals(3, $data);


        $data = $redis->lRange($key[1], 0, 3);
        $this->assertEquals([$value[1], $value[2], $value[0]], $data);

        $data = $redis->lPop($key[1]);
        $this->assertEquals($value[1], $data);

        $data = $redis->rPop($key[1]);
        $this->assertEquals($value[0], $data);

        $data = $redis->lPuShx($key[1], 'x');
        $this->assertEquals($redis->lLen($key[1]), $data);
        $this->assertEquals('x', $redis->lPop($key[1]));

        $data = $redis->rPuShx($key[1], 'z');
        $this->assertEquals($redis->lLen($key[1]), $data);
        $this->assertEquals('z', $redis->rPop($key[1]));

        $redis->del($key[1]);
        $redis->rPush($key[1], $value[0], $value[0], $value[0]);
        $data = $redis->lRem($key[1], 1, $value[0]);
        $this->assertEquals(1, $data);

        $data = $redis->lSet($key[1], 0, 'xx');
        $this->assertTrue($data);
        $this->assertEquals('xx', $redis->lPop($key[1]));

        $data = $redis->lTrim($key[1], 0, 2);
        $this->assertTrue($data);
        $this->assertEquals(1, $redis->lLen($key[1]));
    }

    /**
     * 集合测试
     * testMuster
     * @author Tioncico
     * Time: 9:10
     */
    function testMuster()
    {
        $redis = $this->redis;
        $key = [
            'muster1',
            'muster2',
            'muster3',
            'muster4',
            'muster5',
        ];
        $value = [
            '1',
            '2',
            '3',
            '4',
        ];

        $redis->del($key[0]);
        $redis->del($key[1]);
        $data = $redis->sAdd($key[0], $value[0], $value[1]);
        $this->assertEquals(2, $data);

        $data = $redis->sCard($key[0]);
        $this->assertEquals(2, $data);

        $redis->sAdd($key[1], $value[0], $value[2]);

        $data = $redis->sDiff($key[0], $key[1]);
        $this->assertEquals([$value[1]], $data);

        $data = $redis->sDiff($key[1], $key[0]);
        $this->assertEquals([$value[2]], $data);

        $data = $redis->sMembers($key[0]);
        $this->assertEquals([$value[0], $value[1]], $data);
        $data = $redis->sMembers($key[1]);
        $this->assertEquals([$value[0], $value[2]], $data);

        $data = $redis->sDiffStore($key[2], $key[0], $key[1]);
        $this->assertEquals(1, $data);
        $data = $redis->sMembers($key[2]);
        $this->assertEquals([$value[1]], $data);

        $data = $redis->sInter($key[0], $key[1]);
        $this->assertEquals([$value[0]], $data);

        $data = $redis->sInterStore($key[3], $key[0], $key[1]);
        $this->assertEquals(1, $data);
        $this->assertEquals([$value[0]], $redis->sMembers($key[3]));

        $data = $redis->sIsMember($key[0], $value[0]);
        $this->assertEquals(1, $data);
        $data = $redis->sIsMember($key[0], $value[3]);
        $this->assertEquals(0, $data);

        $data = $redis->sMove($key[0], $key[1], $value[1]);
        $this->assertEquals(1, $data);

        $data = $redis->sPop($key[0]);
        $this->assertEquals(1, $data);

        $redis->del($key[3]);
        $redis->sAdd($key[3], $value[0], $value[1], $value[2], $value[3]);
        $data = $redis->sRandMemBer($key[3], 4);
        $this->assertEquals(4, count($data));

        $data = $redis->sRen($key[3], $value[0], $value[1], $value[2], $value[3]);
        $this->assertEquals(4, $data);
        $this->assertEquals([], $redis->sMembers($key[3]));

        $data = $redis->sUnion($key[0], $key[1]);
        $this->assertEquals([$value[0], $value[1], $value[2]], $data);

        $redis->del($key[1]);
        $redis->del($key[2]);
        $redis->del($key[3]);
        $redis->del($key[4]);
        $redis->sAdd($key[1], 1, 2, 3, 4);
        $redis->sAdd($key[2], 5);
        $redis->sAdd($key[3], 6, 7);
        $data = $redis->sUnIomStore($key[4], $key[1], $key[2], $key[3]);
        $this->assertEquals(7, $data);
//        $data = $redis->sScan('s', 'a', 's');
//        $this->assertEquals(1, $data);
    }

    /**
     * 有序集合测试
     * testSortMuster
     * @author Tioncico
     * Time: 14:17
     */
    function testSortMuster()
    {
        $redis = $this->redis;

        $key = [
            'sortMuster1',
            'sortMuster2',
            'sortMuster3',
            'sortMuster4',
            'sortMuster5',
        ];
        $member = [
            'member1',
            'member2',
            'member3',
            'member4',
            'member5',
        ];
        $score = [
            1,
            2,
            3,
            4,
        ];
        $redis->del($key[0]);
        $data = $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1]);

        $this->assertEquals(2, $data);

        $data = $redis->zCard($key[0]);
        $this->assertEquals(2, $data);

        $data = $redis->zCount($key[0], 0, 3);
        $this->assertEquals(2, $data);

        $data = $redis->zInCrBy($key[0], 1, $member[1]);
        $this->assertEquals($score[1] + 1, $data);

        $redis->del($key[0]);
        $redis->del($key[1]);
        $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1]);
        $redis->zAdd($key[1], $score[0], $member[0], $score[3], $member[3]);
        $data = $redis->zInTerStore($key[2], 2, $key[0], $key[1]);
        $this->assertEquals(1, $data);

        $data = $redis->zLexCount($key[0], '-', '+');
        $this->assertEquals(2, $data);

        $redis->del($key[0]);
        $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1], $score[2], $member[2]);
        $data = $redis->zRange($key[0], 0, -1, true);
        $this->assertEquals([
            $member[0] => $score[0],
            $member[1] => $score[1],
            $member[2] => $score[2],
        ], $data);
        $data = $redis->zRange($key[0], 0, -1, false);
        $this->assertEquals([
            $member[0],
            $member[1],
            $member[2],
        ], $data);

        $data = $redis->zRangeByLex($key[0], '-', '+');
        $this->assertEquals(3, count($data));

        $data = $redis->zRangeByScore($key[0], 2, 3, true);

        $this->assertEquals([
            $member[1] => $score[1],
            $member[2] => $score[2],
        ], $data);

        $data = $redis->zRangeByScore($key[0], 2, 3, false);
        $this->assertEquals([
            $member[1],
            $member[2],
        ], $data);

        $data = $redis->zRank($key[0], $member[1]);
        $this->assertEquals(1, $data);

        $data = $redis->zRem($key[0], $member[1], $member[2]);
        $this->assertEquals(2, $data);

        $redis->del($key[0]);
        $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1], $score[2], $member[2]);
        $data = $redis->zRemRangeByLex($key[0], '-', '+');
        $this->assertEquals(3, $data);

        $redis->del($key[0]);
        $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1], $score[2], $member[2]);
        $data = $redis->zRemRangeByRank($key[0], 0, 2);
        $this->assertEquals(3, $data);

        $redis->del($key[0]);
        $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1], $score[2], $member[2]);
        $data = $redis->zRemRangeByScore($key[0], 0, 3);
        $this->assertEquals(3, $data);


        $redis->del($key[0]);
        $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1], $score[2], $member[2]);
        $data = $redis->zRevRange($key[0], 0, 3);
        $this->assertEquals([
            $member[2],
            $member[1],
            $member[0],
        ], $data);
        $redis->del($key[0]);
        $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1], $score[2], $member[2]);
        $data = $redis->zRevRange($key[0], 0, 3, true);
        $this->assertEquals([
            $member[2] => $score[2],
            $member[1] => $score[1],
            $member[0] => $score[0],
        ], $data);


        $redis->del($key[0]);
        $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1], $score[2], $member[2]);
        $data = $redis->zRevRangeByScore($key[0], 3, 0, true);

        $this->assertEquals([
            $member[2] => $score[2],
            $member[1] => $score[1],
            $member[0] => $score[0],
        ], $data);
        $redis->del($key[0]);
        $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1], $score[2], $member[2]);
        $data = $redis->zReVRangeByScore($key[0], 3, 0, false);
        $this->assertEquals([
            $member[2],
            $member[1],
            $member[0],
        ], $data);

        $data = $redis->zRevRank($key[0], $member[0]);
        $this->assertEquals(2, $data);

        $data = $redis->zScore($key[0], $member[0]);
        $this->assertEquals($score[0], $data);

        $redis->del($key[0]);
        $redis->del($key[1]);
        $redis->del($key[2]);
        $redis->zAdd($key[0], $score[0], $member[0], $score[1], $member[1]);
        $redis->zAdd($key[1], $score[0], $member[0], $score[3], $member[3]);
        $data = $redis->zUnionStore($key[2], 2, $key[1], $key[0]);
        $this->assertEquals(3, $data);
    }

    function testHyperLog()
    {
        $redis = $this->redis;

        $key = [
            'hp1',
            'hp2',
            'hp3',
            'hp4',
            'hp5',
        ];
        $redis->del($key[0]);
        $redis->del($key[1]);
        $data = $redis->pfAdd($key[0], ...[1, 2, 2, 3, 3]);
        $this->assertEquals(1, $data);

        $redis->pfAdd($key[1], ...[1, 2, 2, 3, 3]);
        $data = $redis->pfCount($key[0], $key[1]);
        $this->assertEquals(3, $data);

        $data = $redis->pfMerge($key[2], $key[0], $key[1]);
        $this->assertEquals(1, $data);
    }

    function testSubscribe()
    {
        $redis = $this->redis;
        $key = 'redisChat';
//        $data = $redis->pSubscribe($key);
//        var_dump($data);
        $this->assertEquals(1, 1);

//        $data = $redis->pUbSub('a','b','v');
//        $this->assertEquals(1,$data);
//
//        $data = $redis->publish('a','f');
//        $this->assertEquals(1,$data);
//
//        $data = $redis->pUnSubscribe('a','v');
//        $this->assertEquals(1,$data);
//
//        $data = $redis->subscribe('a','s');
//        $this->assertEquals(1,$data);
//
//        $data = $redis->unsubscribe('a','sdf');
//        $this->assertEquals(1,$data);
    }

    function testWatch()
    {

        $redis = $this->redis;

        $data = $redis->multi();
        $this->assertTrue($data);
        $data = $redis->set('a', 1);
        var_dump($data);
        $data = $redis->set('b', 1);
        var_dump($data);
        $data = $redis->set('c', 1);
        var_dump($data);
        $data = $redis->get('a', 1);
        var_dump($data);
        $data = $redis->get('b', 1);
        var_dump($data);
        $data = $redis->get('c', 1);
        var_dump($data);
        $data = $redis->exec();
        var_dump($data);
        $this->assertEquals(1, 1);

//        $data = $redis->discard();
//        $this->assertEquals(1, $data);
//        $data = $redis->unwatch();
//        $this->assertEquals(1, $data);
//        $data = $redis->watch();
    }


    function testScript(){

        $redis = $this->redis;

        $data = $redis->eval('s','s','a','1','2','a');
        $this->assertEquals(1,$data);

        $data = $redis->evalsha('a','g','g','1','a','a');
        $this->assertEquals(1,$data);

        $data = $redis->scriptExists('a','f');
        $this->assertEquals(1,$data);

        $data = $redis->scriptFlush();
        $this->assertEquals(1,$data);

        $data = $redis->scriptKill();
        $this->assertEquals(1,$data);

        $data = $redis->scriptLoad('a');
        $this->assertEquals(1,$data);
    }

}
