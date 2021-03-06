<?php

namespace DoctrineModuleTest\Cache;

use DoctrineModule\Cache\DoctrineCacheStorage;
use Doctrine\Common\Cache\ArrayCache;

use Zend\Cache\Storage\Adapter\AdapterOptions;

use PHPUnit_Framework_TestCase;
use Zend\Stdlib\ErrorHandler;

/**
 * Tests for the cache bridge
 *
 * @license MIT
 * @link    http://www.doctrine-project.org/
 * @author  Marco Pivetta <ocramius@gmail.com>
 * @todo extend \ZendTest\Cache\Storage\CommonAdapterTest instead
 *
 * @covers \DoctrineModule\Cache\DoctrineCacheStorage
 */
class DoctrineCacheStorageTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var AdapterOptions
     */
    protected $options;

    /**
     * The storage adapter
     *
     * @var \Zend\Cache\Storage\StorageInterface
     */
    protected $storage;

    /**
     * All datatypes of PHP
     *
     * @var string[]
     */
    protected $phpDatatypes = ['NULL', 'boolean', 'integer', 'double', 'string', 'array', 'object', 'resource'];

    public function setUp()
    {
        $this->options = new AdapterOptions();
        // @todo fix constructor as it is messy
        $this->storage = new DoctrineCacheStorage($this->options, new ArrayCache());

        $this->assertInstanceOf(
            'Zend\Cache\Storage\StorageInterface',
            $this->storage,
            'Storage adapter instance is needed for tests'
        );
        $this->assertInstanceOf(
            'Zend\Cache\Storage\Adapter\AdapterOptions',
            $this->options,
            'Options instance is needed for tests'
        );
    }

    public function tearDown()
    {
        // be sure the error handler has been stopped
        if (ErrorHandler::started()) {
            ErrorHandler::stop();
            $this->fail('ErrorHandler not stopped');
        }
    }

    public function testOptionNamesValid()
    {
        $options = $this->storage->getOptions()->toArray();
        foreach ($options as $name => $value) {
            $this->assertRegExp(
                '/^[a-z]+[a-z0-9_]*[a-z0-9]+$/',
                $name,
                "Invalid option name '{$name}'"
            );
        }
    }

    public function testGettersAndSettersOfOptionsExists()
    {
        $options = $this->storage->getOptions();
        foreach ($options->toArray() as $option => $value) {
            if ($option == 'adapter') {
                // Skip this, as it's a "special" value
                continue;
            }
            $method = ucwords(str_replace('_', ' ', $option));
            $method = str_replace(' ', '', $method);

            $this->assertTrue(
                method_exists($options, 'set' . $method),
                "Missing method 'set'{$method}"
            );

            $this->assertTrue(
                method_exists($options, 'get' . $method),
                "Missing method 'get'{$method}"
            );
        }
    }

    public function testOptionsGetAndSetDefault()
    {
        $options = $this->storage->getOptions();
        $this->storage->setOptions($options);
        $this->assertSame($options, $this->storage->getOptions());
    }

    public function testOptionsFluentInterface()
    {
        $options = $this->storage->getOptions();
        foreach ($options->toArray() as $option => $value) {
            $method = ucwords(str_replace('_', ' ', $option));
            $method = 'set' . str_replace(' ', '', $method);
            $this->assertSame(
                $options,
                $options->{$method}($value),
                "Method '{$method}' doesn't implement the fluent interface"
            );
        }

        $this->assertSame(
            $this->storage,
            $this->storage->setOptions($options),
            "Method 'setOptions' doesn't implement the fluent interface"
        );
    }

    public function testGetCapabilities()
    {
        $capabilities = $this->storage->getCapabilities();
        $this->assertInstanceOf('Zend\Cache\Storage\Capabilities', $capabilities);
    }

    public function testDatatypesCapability()
    {
        $capabilities = $this->storage->getCapabilities();
        $datatypes    = $capabilities->getSupportedDatatypes();
        $this->assertInternalType('array', $datatypes);

        foreach ($datatypes as $sourceType => $targetType) {
            $this->assertContains($sourceType, $this->phpDatatypes, 'Unknown source type "' . $sourceType . '"');

            if (is_string($targetType)) {
                $this->assertContains($targetType, $this->phpDatatypes, 'Unknown source type "' . $sourceType . '"');
            } else {
                $this->assertInternalType('bool', $targetType, 'Target type must be a string or boolean');
            }
        }
    }

    public function testSupportedMetadataCapability()
    {
        $capabilities = $this->storage->getCapabilities();
        $metadata     = $capabilities->getSupportedMetadata();
        $this->assertInternalType('array', $metadata);

        foreach ($metadata as $property) {
            $this->assertInternalType('string', $property);
        }
    }

    public function testTtlCapabilities()
    {
        $capabilities = $this->storage->getCapabilities();

        $this->assertInternalType('integer', $capabilities->getMaxTtl());
        $this->assertGreaterThanOrEqual(0, $capabilities->getMaxTtl());

        $this->assertInternalType('bool', $capabilities->getStaticTtl());

        $this->assertInternalType('numeric', $capabilities->getTtlPrecision());
        $this->assertGreaterThan(0, $capabilities->getTtlPrecision());

        $this->assertInternalType('bool', $capabilities->getStaticTtl());
    }

    public function testKeyCapabilities()
    {
        $capabilities = $this->storage->getCapabilities();

        $this->assertInternalType('integer', $capabilities->getMaxKeyLength());
        $this->assertGreaterThanOrEqual(-1, $capabilities->getMaxKeyLength());

        $this->assertInternalType('bool', $capabilities->getNamespaceIsPrefix());

        $this->assertInternalType('string', $capabilities->getNamespaceSeparator());
    }

    public function testHasItemReturnsTrueOnValidItem()
    {
        $this->assertTrue($this->storage->setItem('key', 'value'));
        $this->assertTrue($this->storage->hasItem('key'));
    }

    public function testHasItemReturnsFalseOnMissingItem()
    {
        $this->assertFalse($this->storage->hasItem('key'));
    }

    public function testHasItemNonReadable()
    {
        $this->assertTrue($this->storage->setItem('key', 'value'));

        $this->options->setReadable(false);
        $this->assertFalse($this->storage->hasItem('key'));
    }

    public function testHasItemsReturnsKeysOfFoundItems()
    {
        $this->assertTrue($this->storage->setItem('key1', 'value1'));
        $this->assertTrue($this->storage->setItem('key2', 'value2'));

        $result = $this->storage->hasItems(['missing', 'key1', 'key2']);
        sort($result);

        $expectedResult = ['key1', 'key2'];
        $this->assertEquals($expectedResult, $result);
    }

    public function testHasItemsReturnsEmptyArrayIfNonReadable()
    {
        $this->assertTrue($this->storage->setItem('key', 'value'));

        $this->options->setReadable(false);
        $this->assertEquals([], $this->storage->hasItems(['key']));
    }

    public function testGetItemReturnsNullOnMissingItem()
    {
        $this->assertNull($this->storage->getItem('unknown'));
    }

    public function testGetItemSetsSuccessFlag()
    {
        $success = null;

        // $success = false on get missing item
        $this->storage->getItem('unknown', $success);
        $this->assertFalse($success);

        // $success = true on get valid item
        $this->storage->setItem('test', 'test');
        $this->storage->getItem('test', $success);
        $this->assertTrue($success);
    }

    public function testGetItemReturnsNullIfNonReadable()
    {
        $this->options->setReadable(false);

        $this->assertTrue($this->storage->setItem('key', 'value'));
        $this->assertNull($this->storage->getItem('key'));
    }

    public function testGetItemsReturnsKeyValuePairsOfFoundItems()
    {
        $this->assertTrue($this->storage->setItem('key1', 'value1'));
        $this->assertTrue($this->storage->setItem('key2', 'value2'));

        $result = $this->storage->getItems(['missing', 'key1', 'key2']);
        ksort($result);

        $expectedResult = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetItemsReturnsEmptyArrayIfNonReadable()
    {
        $this->options->setReadable(false);

        $this->assertTrue($this->storage->setItem('key', 'value'));
        $this->assertEquals([], $this->storage->getItems(['key']));
    }

    public function testGetMetadata()
    {
        $capabilities       = $this->storage->getCapabilities();
        $supportedMetadatas = $capabilities->getSupportedMetadata();

        $this->assertTrue($this->storage->setItem('key', 'value'));
        $metadata = $this->storage->getMetadata('key');

        $this->assertInternalType('array', $metadata);
        foreach ($supportedMetadatas as $supportedMetadata) {
            $this->assertArrayHasKey($supportedMetadata, $metadata);
        }
    }

    public function testGetMetadataReturnsFalseOnMissingItem()
    {
        $this->assertFalse($this->storage->getMetadata('unknown'));
    }

    public function testGetMetadataReturnsFalseIfNonReadable()
    {
        $this->options->setReadable(false);

        $this->assertTrue($this->storage->setItem('key', 'value'));
        $this->assertFalse($this->storage->getMetadata('key'));
    }

    public function testGetMetadatas()
    {
        $capabilities       = $this->storage->getCapabilities();
        $supportedMetadatas = $capabilities->getSupportedMetadata();

        $items = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        $this->assertSame([], $this->storage->setItems($items));

        $metadatas = $this->storage->getMetadatas(array_keys($items));
        $this->assertInternalType('array', $metadatas);
        $this->assertSame(count($items), count($metadatas));
        foreach ($metadatas as $metadata) {
            $this->assertInternalType('array', $metadata);
            foreach ($supportedMetadatas as $supportedMetadata) {
                $this->assertArrayHasKey($supportedMetadata, $metadata);
            }
        }
    }

    public function testGetMetadatasReturnsEmptyArrayIfNonReadable()
    {
        $this->options->setReadable(false);

        $this->assertTrue($this->storage->setItem('key', 'value'));
        $this->assertEquals([], $this->storage->getMetadatas(['key']));
    }

    public function testSetGetHasAndRemoveItem()
    {
        $this->assertTrue($this->storage->setItem('key', 'value'));
        $this->assertEquals('value', $this->storage->getItem('key'));
        $this->assertTrue($this->storage->hasItem('key'));

        $this->assertTrue($this->storage->removeItem('key'));
        $this->assertFalse($this->storage->hasItem('key'));
        $this->assertNull($this->storage->getItem('key'));
    }

    public function testSetGetHasAndRemoveItems()
    {
        $items = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->assertSame([], $this->storage->setItems($items));

        $rs = $this->storage->getItems(array_keys($items));
        $this->assertInternalType('array', $rs);
        foreach ($items as $key => $value) {
            $this->assertArrayHasKey($key, $rs);
            $this->assertEquals($value, $rs[$key]);
        }

        $rs = $this->storage->hasItems(array_keys($items));
        $this->assertInternalType('array', $rs);
        $this->assertEquals(count($items), count($rs));
        foreach ($items as $key => $value) {
            $this->assertContains($key, $rs);
        }

        $this->assertSame(['missing'], $this->storage->removeItems(['missing', 'key1', 'key3']));
        unset($items['key1'], $items['key3']);

        $rs = $this->storage->getItems(array_keys($items));
        $this->assertInternalType('array', $rs);
        foreach ($items as $key => $value) {
            $this->assertArrayHasKey($key, $rs);
            $this->assertEquals($value, $rs[$key]);
        }

        $rs = $this->storage->hasItems(array_keys($items));
        $this->assertInternalType('array', $rs);
        $this->assertEquals(count($items), count($rs));
        foreach ($items as $key => $value) {
            $this->assertContains($key, $rs);
        }
    }

    public function testSetGetHasAndRemoveItemWithNamespace()
    {
        // write "key" to default namespace
        $this->options->setNamespace('defaultns1');
        $this->assertTrue($this->storage->setItem('key', 'defaultns1'));

        // write "key" to an other default namespace
        $this->options->setNamespace('defaultns2');
        $this->assertTrue($this->storage->setItem('key', 'defaultns2'));

        // test value of defaultns2
        $this->assertTrue($this->storage->hasItem('key'));
        $this->assertEquals('defaultns2', $this->storage->getItem('key'));

        // test value of defaultns1
        $this->options->setNamespace('defaultns1');
        $this->assertTrue($this->storage->hasItem('key'));
        $this->assertEquals('defaultns1', $this->storage->getItem('key'));

        // remove item of defaultns1
        $this->options->setNamespace('defaultns1');
        $this->assertTrue($this->storage->removeItem('key'));
        $this->assertFalse($this->storage->hasItem('key'));

        // remove item of defaultns2
        $this->options->setNamespace('defaultns2');
        $this->assertTrue($this->storage->removeItem('key'));
        $this->assertFalse($this->storage->hasItem('key'));
    }

    public function testSetGetHasAndRemoveItemsWithNamespace()
    {
        $items = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->options->setNamespace('defaultns1');
        $this->assertSame([], $this->storage->setItems($items));

        $this->options->setNamespace('defaultns2');
        $this->assertSame([], $this->storage->hasItems(array_keys($items)));

        $this->options->setNamespace('defaultns1');
        $rs = $this->storage->getItems(array_keys($items));
        $this->assertInternalType('array', $rs);
        foreach ($items as $key => $value) {
            $this->assertArrayHasKey($key, $rs);
            $this->assertEquals($value, $rs[$key]);
        }

        $rs = $this->storage->hasItems(array_keys($items));
        $this->assertInternalType('array', $rs);
        $this->assertEquals(count($items), count($rs));
        foreach ($items as $key => $value) {
            $this->assertContains($key, $rs);
        }

        // remove the first and the last item
        $this->assertSame(['missing'], $this->storage->removeItems(['missing', 'key1', 'key3']));
        unset($items['key1'], $items['key3']);

        $rs = $this->storage->getItems(array_keys($items));
        $this->assertInternalType('array', $rs);
        foreach ($items as $key => $value) {
            $this->assertArrayHasKey($key, $rs);
            $this->assertEquals($value, $rs[$key]);
        }

        $rs = $this->storage->hasItems(array_keys($items));
        $this->assertInternalType('array', $rs);
        $this->assertEquals(count($items), count($rs));
        foreach ($items as $key => $value) {
            $this->assertContains($key, $rs);
        }
    }

    public function testSetAndGetItemOfDifferentTypes()
    {
        $capabilities = $this->storage->getCapabilities();

        $types = [
            'NULL'     => null,
            'boolean'  => true,
            'integer'  => 12345,
            'double'   => 123.45,
            'string'   => 'string', // already tested
            'array'    => ['one', 'tow' => 'two', 'three' => ['four' => 'four']],
            'object'   => new \stdClass(),
            'resource' => fopen(__FILE__, 'r'),
        ];

        $types['object']->one        = 'one';
        $types['object']->two        = new \stdClass();
        $types['object']->two->three = 'three';

        foreach ($capabilities->getSupportedDatatypes() as $sourceType => $targetType) {
            if ($targetType === false) {
                continue;
            }

            $value = $types[$sourceType];
            $this->assertTrue($this->storage->setItem('key', $value), "Failed to set type '{$sourceType}'");

            if ($targetType === true) {
                $this->assertSame($value, $this->storage->getItem('key'));
            } elseif (is_string($targetType)) {
                settype($value, $targetType);
                $this->assertEquals($value, $this->storage->getItem('key'));
            }
        }
    }

    public function testSetItemReturnsFalseIfNonWritable()
    {
        $this->options->setWritable(false);

        $this->assertFalse($this->storage->setItem('key', 'value'));
        $this->assertFalse($this->storage->hasItem('key'));
    }

    public function testAddNewItem()
    {
        $this->assertTrue($this->storage->addItem('key', 'value'));
        $this->assertTrue($this->storage->hasItem('key'));
    }

    public function testAddItemReturnsFalseIfItemAlreadyExists()
    {
        $this->assertTrue($this->storage->setItem('key', 'value'));
        $this->assertFalse($this->storage->addItem('key', 'newValue'));
    }

    public function testAddItemReturnsFalseIfNonWritable()
    {
        $this->options->setWritable(false);

        $this->assertFalse($this->storage->addItem('key', 'value'));
        $this->assertFalse($this->storage->hasItem('key'));
    }

    public function testAddItemsReturnsFailedKeys()
    {
        $this->assertTrue($this->storage->setItem('key1', 'value1'));

        $failedKeys = $this->storage->addItems([
            'key1' => 'XYZ',
            'key2' => 'value2',
        ]);

        $this->assertSame(['key1'], $failedKeys);
        $this->assertSame('value1', $this->storage->getItem('key1'));
        $this->assertTrue($this->storage->hasItem('key2'));
    }

    public function testReplaceExistingItem()
    {
        $this->assertTrue($this->storage->setItem('key', 'value'));
        $this->assertTrue($this->storage->replaceItem('key', 'anOtherValue'));
        $this->assertEquals('anOtherValue', $this->storage->getItem('key'));
    }

    public function testReplaceItemReturnsFalseOnMissingItem()
    {
        $this->assertFalse($this->storage->replaceItem('missingKey', 'value'));
    }

    public function testReplaceItemReturnsFalseIfNonWritable()
    {
        $this->storage->setItem('key', 'value');
        $this->options->setWritable(false);

        $this->assertFalse($this->storage->replaceItem('key', 'newvalue'));
        $this->assertEquals('value', $this->storage->getItem('key'));
    }

    public function testReplaceItemsReturnsFailedKeys()
    {
        $this->assertTrue($this->storage->setItem('key1', 'value1'));

        $failedKeys = $this->storage->replaceItems([
            'key1' => 'XYZ',
            'key2' => 'value2',
        ]);

        $this->assertSame(['key2'], $failedKeys);
        $this->assertSame('XYZ', $this->storage->getItem('key1'));
        $this->assertFalse($this->storage->hasItem('key2'));
    }

    public function testRemoveItemReturnsFalseOnMissingItem()
    {
        $this->assertFalse($this->storage->removeItem('missing'));
    }

    public function testRemoveItemsReturnsMissingKeys()
    {
        $this->storage->setItem('key', 'value');
        $this->assertSame(['missing'], $this->storage->removeItems(['key', 'missing']));
    }

    public function testCheckAndSetItem()
    {
        $this->assertTrue($this->storage->setItem('key', 'value'));

        $success  = null;
        $casToken = null;
        $this->assertEquals('value', $this->storage->getItem('key', $success, $casToken));
        $this->assertNotNull($casToken);

        $this->assertTrue($this->storage->checkAndSetItem($casToken, 'key', 'newValue'));
        $this->assertFalse($this->storage->checkAndSetItem($casToken, 'key', 'failedValue'));
        $this->assertEquals('newValue', $this->storage->getItem('key'));
    }

    public function testIncrementItem()
    {
        $this->assertTrue($this->storage->setItem('counter', 10));
        $this->assertEquals(15, $this->storage->incrementItem('counter', 5));
        $this->assertEquals(15, $this->storage->getItem('counter'));
    }

    public function testIncrementItemInitialValue()
    {
        $this->assertEquals(5, $this->storage->incrementItem('counter', 5));
        $this->assertEquals(5, $this->storage->getItem('counter'));
    }

    public function testIncrementItemReturnsFalseIfNonWritable()
    {
        $this->storage->setItem('key', 10);
        $this->options->setWritable(false);

        $this->assertFalse($this->storage->incrementItem('key', 5));
        $this->assertEquals(10, $this->storage->getItem('key'));
    }

    public function testIncrementItemsReturnsKeyValuePairsOfWrittenItems()
    {
        $this->assertTrue($this->storage->setItem('key1', 10));

        $result = $this->storage->incrementItems(['key1' => 10, 'key2' => 10]);

        ksort($result);

        $this->assertSame(['key1' => 20, 'key2' => 10], $result);
    }

    public function testIncrementItemsReturnsEmptyArrayIfNonWritable()
    {
        $this->storage->setItem('key', 10);
        $this->options->setWritable(false);

        $this->assertSame([], $this->storage->incrementItems(['key' => 5]));
        $this->assertEquals(10, $this->storage->getItem('key'));
    }

    public function testDecrementItem()
    {
        $this->assertTrue($this->storage->setItem('counter', 30));
        $this->assertEquals(25, $this->storage->decrementItem('counter', 5));
        $this->assertEquals(25, $this->storage->getItem('counter'));
    }

    public function testDecrementItemInitialValue()
    {
        $this->assertEquals(-5, $this->storage->decrementItem('counter', 5));
        $this->assertEquals(-5, $this->storage->getItem('counter'));
    }

    public function testDecrementItemReturnsFalseIfNonWritable()
    {
        $this->storage->setItem('key', 10);
        $this->options->setWritable(false);

        $this->assertFalse($this->storage->decrementItem('key', 5));
        $this->assertEquals(10, $this->storage->getItem('key'));
    }

    public function testDecrementItemsReturnsEmptyArrayIfNonWritable()
    {
        $this->storage->setItem('key', 10);
        $this->options->setWritable(false);

        $this->assertSame([], $this->storage->decrementItems(['key' => 5]));
        $this->assertEquals(10, $this->storage->getItem('key'));
    }

    public function testTouchItemReturnsFalseOnMissingItem()
    {
        $this->assertFalse($this->storage->touchItem('missing'));
    }

    public function testTouchItemReturnsFalseIfNonWritable()
    {
        $this->options->setWritable(false);

        $this->assertFalse($this->storage->touchItem('key'));
    }

    public function testTouchItemsReturnsGivenKeysIfNonWritable()
    {
        $this->options->setWritable(false);
        $this->assertSame(['key'], $this->storage->touchItems(['key']));
    }

    public function testSetItemAndSetItemsCallSaveWithTtl()
    {
        $ttl = rand();

        $provider = $this->getMock('Doctrine\Common\Cache\ArrayCache');
        $provider->expects($this->exactly(4))
                 ->method('save')
                 ->with($this->anything(), $this->anything(), $ttl);

        $this->storage = new DoctrineCacheStorage($this->options, $provider);

        $this->options->setTtl($ttl);
        $this->storage->setItem('key', 'value');

        $items = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        $this->storage->setItems($items);
    }
}
