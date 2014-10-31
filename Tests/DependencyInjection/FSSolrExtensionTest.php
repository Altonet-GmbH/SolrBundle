<?php

namespace FS\SolrBundle\Tests\DependencyInjection;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use FS\SolrBundle\DependencyInjection\FSSolrExtension;
use Symfony\Component\DependencyInjection\Reference;

/**
 *
 * @group extension
 */
class FSSolrExtensionTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ContainerBuilder
     */
    private $container = null;

    public function setUp()
    {
        $this->container = new ContainerBuilder();
    }

    private function enableOdmConfig()
    {
        $this->container->setParameter('doctrine_mongodb.odm.document_managers', array('default'=>'odm.default.mananger'));
    }

    private function enableOrmConfig()
    {
        $this->container->setParameter('doctrine.entity_managers', array('default'=>'orm.default.mananger'));
    }

    private function commonConfig()
    {
        return array(array(

            'endpoints' => array(
                'default' => array(
                    'host' => '192.168.178.24',
                    'port' => 8983,
                    'path' => '/solr/',
                )
            )
        ));
    }

    public function testDoctrineORMSetup()
    {
        $this->enableOrmConfig();
        $config = $this->commonConfig();

        $extension = new FSSolrExtension();
        $extension->load($config, $this->container);

        $this->assertTrue($this->container->has('solr.update.document.orm.listener'), 'update listener');
        $this->assertTrue($this->container->has('solr.delete.document.orm.listener'), 'delete listener');
        $this->assertTrue($this->container->has('solr.add.document.orm.listener'), 'insert listener');

        $this->assertDefinitionHasTag('solr.update.document.orm.listener', 'doctrine.event_listener');
        $this->assertDefinitionHasTag('solr.delete.document.orm.listener', 'doctrine.event_listener');
        $this->assertDefinitionHasTag('solr.add.document.orm.listener', 'doctrine.event_listener');

        $this->assertClassnameResolverHasOrmDefaultConfiguration();
    }

    public function testDoctrineODMSetup()
    {
        $config = $this->commonConfig();
        $this->enableOdmConfig();

        $extension = new FSSolrExtension();
        $extension->load($config, $this->container);

        $this->assertTrue($this->container->has('solr.update.document.odm.listener'), 'update listener');
        $this->assertTrue($this->container->has('solr.delete.document.odm.listener'), 'delete listener');
        $this->assertTrue($this->container->has('solr.add.document.odm.listener'), 'insert listener');

        $this->assertDefinitionHasTag('solr.update.document.odm.listener', 'doctrine_mongodb.odm.event_listener');
        $this->assertDefinitionHasTag('solr.delete.document.odm.listener', 'doctrine_mongodb.odm.event_listener');
        $this->assertDefinitionHasTag('solr.add.document.odm.listener', 'doctrine_mongodb.odm.event_listener');

        $this->assertClassnameResolverHasOdmDefaultConfiguration();
    }

    /**
     * @test
     */
    public function solrListensToOdmAndOrmEvents()
    {
        $config = $this->commonConfig();
        $this->enableOdmConfig();
        $this->enableOrmConfig();

        $extension = new FSSolrExtension();
        $extension->load($config, $this->container);

        $this->assertTrue($this->container->has('solr.update.document.odm.listener'), 'update listener');
        $this->assertTrue($this->container->has('solr.delete.document.odm.listener'), 'delete listener');
        $this->assertTrue($this->container->has('solr.add.document.odm.listener'), 'insert listener');

        $this->assertDefinitionHasTag('solr.update.document.odm.listener', 'doctrine_mongodb.odm.event_listener');
        $this->assertDefinitionHasTag('solr.delete.document.odm.listener', 'doctrine_mongodb.odm.event_listener');
        $this->assertDefinitionHasTag('solr.add.document.odm.listener', 'doctrine_mongodb.odm.event_listener');

        $this->assertTrue($this->container->has('solr.update.document.orm.listener'), 'update listener');
        $this->assertTrue($this->container->has('solr.delete.document.orm.listener'), 'delete listener');
        $this->assertTrue($this->container->has('solr.add.document.orm.listener'), 'insert listener');

        $this->assertDefinitionHasTag('solr.update.document.orm.listener', 'doctrine.event_listener');
        $this->assertDefinitionHasTag('solr.delete.document.orm.listener', 'doctrine.event_listener');
        $this->assertDefinitionHasTag('solr.add.document.orm.listener', 'doctrine.event_listener');
    }

    private function assertClassnameResolverHasOrmDefaultConfiguration()
    {
        $doctrineConfiguration = $this->getReferenzIdOfCalledMethod();

        $this->assertEquals('doctrine.orm.default_configuration', $doctrineConfiguration);
    }

    private function assertClassnameResolverHasOdmDefaultConfiguration()
    {
        $doctrineConfiguration = $this->getReferenzIdOfCalledMethod();

        $this->assertEquals('doctrine_mongodb.odm.default_configuration', $doctrineConfiguration);
    }

    /**
     * @return Reference
     */
    private function getReferenzIdOfCalledMethod()
    {
        $methodCalls = $this->container->getDefinition('solr.doctrine.classnameresolver.known_entity_namespaces')->getMethodCalls();

        $firstMethodCall = $methodCalls[0];
        $references = $firstMethodCall[1];
        $reference = $references[0];

        return $reference;
    }

    private function assertDefinitionHasTag($definition, $tag)
    {
        $tags = $this->container->getDefinition($definition)->getTags();

        $this->assertTrue(
            $this->container->getDefinition($definition)->hasTag($tag),
            sprintf('%s with %s tag, has %s', $definition, $tag, print_r($tags, true))
        );
    }
}

