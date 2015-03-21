<?php

namespace FS\SolrBundle\Tests;

use FS\SolrBundle\Tests\Doctrine\Annotation\Entities\InvalidTestEntityFiltered;
use FS\SolrBundle\Tests\Doctrine\Annotation\Entities\ValidTestEntityFiltered;
use FS\SolrBundle\Tests\Doctrine\Mapper\SolrDocumentStub;
use FS\SolrBundle\Tests\ResultFake;
use FS\SolrBundle\Tests\SolrResponseFake;
use FS\SolrBundle\Query\FindByDocumentNameQuery;
use FS\SolrBundle\Event\EventManager;
use FS\SolrBundle\Tests\SolrClientFake;
use FS\SolrBundle\Tests\Doctrine\Mapper\ValidTestEntity;
use FS\SolrBundle\Tests\Doctrine\Annotation\Entities\EntityWithRepository;
use FS\SolrBundle\Doctrine\Mapper\MetaInformation;
use FS\SolrBundle\Tests\Util\MetaTestInformationFactory;
use FS\SolrBundle\Solr;
use FS\SolrBundle\Tests\Doctrine\Annotation\Entities\ValidEntityRepository;
use FS\SolrBundle\Tests\Util\CommandFactoryStub;
use FS\SolrBundle\Query\SolrQuery;
use Solarium\QueryType\Update\Query\Document\Document;

/**
 *
 * @group facade
 */
class SolrTest extends \PHPUnit_Framework_TestCase
{

    protected $metaFactory = null;
    protected $config = null;
    protected $commandFactory = null;
    protected $eventDispatcher = null;
    protected $mapper = null;
    protected $solrClientFake = null;

    public function setUp()
    {
        $this->metaFactory = $metaFactory = $this->getMock(
            'FS\SolrBundle\Doctrine\Mapper\MetaInformationFactory',
            array(),
            array(),
            '',
            false
        );
        $this->config = $this->getMock('FS\SolrBundle\SolrConnection', array(), array(), '', false);
        $this->commandFactory = CommandFactoryStub::getFactoryWithAllMappingCommand();
        $this->eventDispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcher', array(), array(), '', false);
        $this->mapper = $this->getMock('FS\SolrBundle\Doctrine\Mapper\EntityMapper', array(), array(), '', false);

        $this->solrClientFake = $this->getMock('Solarium\Client', array(), array(), '', false);
    }

    private function assertUpdateQueryExecuted()
    {
        $updateQuery = $this->getMock('Solarium\QueryType\Update\Query\Query', array(), array(), '', false);
        $updateQuery->expects($this->once())
            ->method('addDocument');

        $updateQuery->expects($this->once())
            ->method('addCommit');

        $this->solrClientFake
            ->expects($this->once())
            ->method('createUpdate')
            ->will($this->returnValue($updateQuery));
    }

    private function assertUpdateQueryWasNotExecuted()
    {
        $updateQuery = $this->getMock('Solarium\QueryType\Update\Query\Query', array(), array(), '', false);
        $updateQuery->expects($this->never())
            ->method('addDocument');

        $updateQuery->expects($this->never())
            ->method('addCommit');

        $this->solrClientFake
            ->expects($this->never())
            ->method('createUpdate');
    }

    protected function assertDeleteQueryWasExecuted()
    {
        $deleteQuery = $this->getMock('Solarium\QueryType\Update\Query\Query', array(), array(), '', false);
        $deleteQuery->expects($this->once())
            ->method('addDeleteQuery')
            ->with($this->isType('string'));

        $deleteQuery->expects($this->once())
            ->method('addCommit');

        $this->solrClientFake
            ->expects($this->once())
            ->method('createUpdate')
            ->will($this->returnValue($deleteQuery));

        $this->solrClientFake
            ->expects($this->once())
            ->method('update')
            ->with($deleteQuery);
    }

    protected function setupMetaFactoryLoadOneCompleteInformation($metaInformation = null)
    {
        if (null === $metaInformation) {
            $metaInformation = MetaTestInformationFactory::getMetaInformation();
        }

        $this->metaFactory->expects($this->once())
            ->method('loadInformation')
            ->will($this->returnValue($metaInformation));
    }

    public function testCreateQuery_ValidEntity()
    {
        $this->setupMetaFactoryLoadOneCompleteInformation();

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        $query = $solr->createQuery('FSBlogBundle:ValidTestEntity');

        $this->assertTrue($query instanceof SolrQuery);
        $this->assertEquals(4, count($query->getMappedFields()));

    }

    public function testGetRepository_UserdefinedRepository()
    {
        $metaInformation = new MetaInformation();
        $metaInformation->setClassName(get_class(new EntityWithRepository()));
        $metaInformation->setRepository('FS\SolrBundle\Tests\Doctrine\Annotation\Entities\ValidEntityRepository');

        $this->setupMetaFactoryLoadOneCompleteInformation($metaInformation);

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        $actual = $solr->getRepository('Tests:EntityWithRepository');

        $this->assertTrue($actual instanceof ValidEntityRepository);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testGetRepository_UserdefinedInvalidRepository()
    {
        $metaInformation = new MetaInformation();
        $metaInformation->setClassName(get_class(new EntityWithRepository()));
        $metaInformation->setRepository('FS\SolrBundle\Tests\Doctrine\Annotation\Entities\InvalidEntityRepository');

        $this->setupMetaFactoryLoadOneCompleteInformation($metaInformation);

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        $solr->getRepository('Tests:EntityWithInvalidRepository');
    }

    public function testAddDocument()
    {
        $this->assertUpdateQueryExecuted();

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        $this->mapOneDocument();

        $this->setupMetaFactoryLoadOneCompleteInformation();

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        $solr->addDocument(new ValidTestEntity());
    }

    public function testUpdateDocument()
    {
        $this->assertUpdateQueryExecuted();

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        $this->mapOneDocument();

        $this->setupMetaFactoryLoadOneCompleteInformation();

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        $solr->updateDocument(new ValidTestEntity());
    }

    public function testDoNotUpdateDocumentIfDocumentCallbackAvoidIt()
    {
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $this->assertUpdateQueryWasNotExecuted();

        $information = new MetaInformation();
        $information->setSynchronizationCallback('shouldBeIndex');
        $this->setupMetaFactoryLoadOneCompleteInformation($information);

        $filteredEntity = new ValidTestEntityFiltered();
        $filteredEntity->shouldIndex = true;

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        $solr->updateDocument($filteredEntity);
    }

    public function testRemoveDocument()
    {
        $this->assertDeleteQueryWasExecuted();

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        $this->setupMetaFactoryLoadOneCompleteInformation();

        $this->mapper->expects($this->once())
            ->method('toDocument')
            ->will($this->returnValue(new DocumentStub()));

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        $solr->removeDocument(new ValidTestEntity());
    }

    public function testClearIndex()
    {
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        $this->solrClientFake->expects($this->once())
            ->method('getEndpoints')
            ->will($this->returnValue(array('core0' => array())));

        $this->assertDeleteQueryWasExecuted();

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        $solr->clearIndex();
    }

    private function assertQueryWasExecuted($data = array())
    {
        $selectQuery = $this->getMock('Solarium\QueryType\Select\Query\Query', array(), array(), '', false);
        $selectQuery->expects($this->once())
            ->method('setQuery');

        $queryResult = new ResultFake($data);

        $this->solrClientFake
            ->expects($this->once())
            ->method('createSelect')
            ->will($this->returnValue($selectQuery));

        $this->solrClientFake
            ->expects($this->once())
            ->method('select')
            ->with($selectQuery)
            ->will($this->returnValue($queryResult));
    }

    public function testQuery_NoResponseKeyInResponseSet()
    {
        $this->assertQueryWasExecuted();

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);

        $document = new Document();
        $document->addField('document_name_s', 'name');
        $query = new FindByDocumentNameQuery();
        $query->setDocument($document);

        $entities = $solr->query($query);
        $this->assertEquals(0, count($entities));
    }

    public function testQuery_OneDocumentFound()
    {
        $arrayObj = new SolrDocumentStub(array('title_s' => 'title'));

        $this->assertQueryWasExecuted(array($arrayObj));

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);

        $document = new Document();
        $document->addField('document_name_s', 'name');
        $query = new FindByDocumentNameQuery();
        $query->setDocument($document);
        $query->setEntity(new ValidTestEntity());

        $entities = $solr->query($query);
        $this->assertEquals(1, count($entities));
    }

    public function testAddEntity_ShouldNotIndexEntity()
    {
        $this->assertUpdateQueryWasNotExecuted();

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $entity = new ValidTestEntityFiltered();

        $information = new MetaInformation();
        $information->setSynchronizationCallback('shouldBeIndex');
        $this->setupMetaFactoryLoadOneCompleteInformation($information);

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        $solr->addDocument($entity);

        $this->assertTrue($entity->getShouldBeIndexedWasCalled(), 'filter method was not called');
    }

    public function testAddEntity_ShouldIndexEntity()
    {
        $this->assertUpdateQueryExecuted();

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        $entity = new ValidTestEntityFiltered();
        $entity->shouldIndex = true;

        $information = MetaTestInformationFactory::getMetaInformation();
        $information->setSynchronizationCallback('shouldBeIndex');
        $this->setupMetaFactoryLoadOneCompleteInformation($information);

        $this->mapOneDocument();

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        $solr->addDocument($entity);

        $this->assertTrue($entity->getShouldBeIndexedWasCalled(), 'filter method was not called');
    }

    public function testAddEntity_FilteredEntityWithUnknownCallback()
    {
        $this->assertUpdateQueryWasNotExecuted();

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $information = MetaTestInformationFactory::getMetaInformation();
        $information->setSynchronizationCallback('shouldBeIndex');
        $this->setupMetaFactoryLoadOneCompleteInformation($information);

        $solr = new Solr($this->solrClientFake, $this->commandFactory, $this->eventDispatcher, $this->metaFactory, $this->mapper);
        try {
            $solr->addDocument(new InvalidTestEntityFiltered());

            $this->fail('BadMethodCallException expected');
        } catch (\BadMethodCallException $e) {
            $this->assertTrue(true);
        }
    }

    protected function mapOneDocument()
    {
        $this->mapper->expects($this->once())
            ->method('toDocument')
            ->will($this->returnValue($this->getMock('Solarium\QueryType\Update\Query\Document\DocumentInterface')));
    }
}



