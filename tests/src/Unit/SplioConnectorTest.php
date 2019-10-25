<?php

namespace Drupal\Tests\splio\Unit;

use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Provides a series of unit tests for the Splio module.
 *
 * @property mixed splioConnector
 * @coversDefaultClass \Drupal\splio\Services\SplioConnector
 * @group splio
 */
class SplioConnectorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'splio',
    'key',
  ];

  private $mock;

  private $handler;

  private $client;

  private $fakeEntity;

  /**
   * Creates a new processor object for use in the tests.
   */
  public function setUp(): void {
    parent::setUp();

    // Splio field entity schema.
    $this->installEntitySchema('splio_field');

    // Fake Drupal entity.
    $fakeEntity = $this->getMockBuilder(EntityBase::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->fakeEntity = $fakeEntity;

    $entityStorage = $this->getMockBuilder(EntityStorageInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $entityStorage->expects($this->any())
      ->method('load')
      ->willReturn($fakeEntity);

    $entityTypeManager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $entityTypeManager->expects($this->any())
      ->method('getstorage')
      ->willReturn($entityStorage);

    // Services needed for tests.
    $this->splioConnector = $this->container->get('splio.splio_connector');
    $this->container->set('entity_type.manager', $entityTypeManager);
  }

  /**
   * Tests the hasConnection() function.
   */
  public function testApiConnection(): void {

    // Splio server body response.
    $splioResponseOK = [
      "code" => 200,
      "name" => "DATA_api",
      "version" => "1.4",
    ];
    $splioResponseBAD = [
      "code" => 401,
      "name" => "Unauthorized",
      "description" => "bad authentication data (UNIVERSE \/ API_KEY)",
    ];

    // Guzzle mock client.
    $this->mock = new MockHandler([
      new Response(200,
        [
          'Server' => 'Apache',
          'Connection' => 'close',
          'Cache-Control' => 'no-cache',
          'Access-Control-Allow-Origin' => '*',
          'Access-Control-Allow-Credentials' => 'true',
          'Access-Control-Allow-Methods' => 'GET, OPTIONS',
          'Access-Control-Allow-Headers' => 'origin, content-type, accept',
          'Content-Type' => 'application/json; charset=utf-8',
        ],
        json_encode($splioResponseOK),
      ),
      new Response(401,
        [
          'Server' => 'Apache',
          'Connection' => 'close',
          'Cache-Control' => 'no-cache',
          'Access-Control-Allow-Origin' => '*',
          'Access-Control-Allow-Credentials' => 'true',
          'Access-Control-Allow-Methods' => 'GET, OPTIONS',
          'Access-Control-Allow-Headers' => 'origin, content-type, accept',
          'Content-Type' => 'application/json; charset=utf-8',
        ],
        json_encode($splioResponseBAD),
      ),
    ]);
    $this->handler = HandlerStack::create($this->mock);
    $this->client = new Client(['handler' => $this->handler]);

    // Set the fake client to SplioConnector service.
    $this->splioConnector->setClient($this->client);

    // Check connection with Splio with OK response from API.
    $connected = $this->splioConnector->hasConnection();
    self::assertTrue($connected);

    // Check connection with Splio with NON-OK response from API.
    $connected = $this->splioConnector->hasConnection();
    self::assertFalse($connected);
  }

  /**
   * Tests the isSplioEntity() function.
   */
  public function testIsSplioEntity(): void {
    // Test with the fake entity created in setUp.
    $isSplioEntity = $this->splioConnector->isSplioEntity($this->fakeEntity);
    self::assertFalse($isSplioEntity);
  }

  /**
   * Tests the getContactLists() function.
   */
  public function testGetContactLists(): void {

    // Splio body response.
    $splioLists = [
      'lists' =>
        [
          'contactsListOne',
          'contactsListTwo',
        ],
    ];

    // Guzzle mock client.
    $this->mock = new MockHandler([
      new Response(200,
        [
          'Server' => 'Apache',
          'Connection' => 'close',
          'Cache-Control' => 'no-cache',
          'Access-Control-Allow-Origin' => '*',
          'Access-Control-Allow-Credentials' => 'true',
          'Access-Control-Allow-Methods' => 'GET, OPTIONS',
          'Access-Control-Allow-Headers' => 'origin, content-type, accept',
          'Content-Type' => 'application/json; charset=utf-8',

        ],
        json_encode([])),
      new Response(200,
        [
          'Server' => 'Apache',
          'Connection' => 'close',
          'Cache-Control' => 'no-cache',
          'Access-Control-Allow-Origin' => '*',
          'Access-Control-Allow-Credentials' => 'true',
          'Access-Control-Allow-Methods' => 'GET, OPTIONS',
          'Access-Control-Allow-Headers' => 'origin, content-type, accept',
          'Content-Type' => 'application/json; charset=utf-8',

        ],
        json_encode($splioLists)),
    ]);
    $this->handler = HandlerStack::create($this->mock);
    $this->client = new Client(['handler' => $this->handler]);

    // Set the fake client to SplioConnector service.
    $this->splioConnector->setClient($this->client);

    // Splio returns an empty array of contact lists.
    $lists = $this->splioConnector->getContactLists();
    self::assertEmpty($lists);

    // Splio returns a non-empty array of contacts lists.
    $lists = $this->splioConnector->getContactLists();
    self::assertEqual($lists, $splioLists);
  }

  /**
   * Tests the createEntities() function.
   *
   * @todo Create the CreateEntities test.
   */
  public function testCreateEntities(): void {

  }

}
