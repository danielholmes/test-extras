<?php

namespace DHolmes\TestExtras\Database;

use PHPUnit_Framework_TestCase;
use InvalidArgumentException;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use DHolmes\DoctrineExtras\ORM\OperationsHelper;

abstract class DoctrineORMTestCase extends PHPUnit_Framework_TestCase implements FixtureInterface
{
    /** @inheritDoc */
    public function load(ObjectManager $manager)
    {
        $entities = $this->getTestEntities();
        array_walk($entities, array($manager, 'persist'));
        
        $manager->flush();
    }
    
    /** @return array */
    abstract protected function getTestEntities();
    
    /** @var OperationsHelper */
    private $operationsHelper;
    
    /** @return OperationsHelper */
    private function getOperationsHelper()
    {
        if ($this->operationsHelper === null)
        {
            $this->operationsHelper = $this->createOperationsHelper();
        }        
        return $this->operationsHelper;
    }
    
    /** @return OperationsHelper */
    protected function createOperationsHelper()
    {
        $helper = new OperationsHelper();
        $helper->setIsSchemaCacheEnabled(true);
        // Cannot really compare entities properly with this enabled because actual entity not 
        // inserted in session. Might be possible if compare contents (excluding id and timestamps?)
        // but that could be innacurate
        $helper->setIsFixturesCacheEnabled(false);
        return OperationsHelper::createWithCachedSchemaAndFixtures();
    }
    
    /** @return EntityManager */
    abstract protected function getEntityManager();
    
    protected function setUpDatabase()
    {
        $entityManager = $this->getEntityManager();
        $this->getOperationsHelper()->setUpDatabase($entityManager, array($this));
    }
    
    /**
     * @param object $entity
     * @return object 
     */
    protected function ensureEntityManaged($entity)
    {
        $em = $this->getEntityManager();
        $merged = $em->merge($entity);
        if ($em->getUnitOfWork()->isScheduledForInsert($merged))
        {
            $desc = $this->getEntityDescription($entity);
            throw new InvalidArgumentException(sprintf('Entity %s not within manager', $desc));
        }
        return $merged;
    }
    
    /**
     * @param object $expectedEntity
     * @param object $entity 
     */
    protected function assertSameEntities($expectedEntity, $entity)
    {
        $entityManager = $this->getEntityManager();
        
        $expectedEntity = $entityManager->merge($expectedEntity);
        $entity = $entityManager->merge($entity);
        
        $expectedDesc = $this->getEntityDescription($expectedEntity);
        $entityDesc = $this->getEntityDescription($entity);
        
        $assertMessage = sprintf('Entities not the same:' . "\n" . '  %s' . "\n" . '  %s', 
                            $expectedDesc, $entityDesc);
        $this->assertSame($expectedEntity, $entity, $assertMessage);
    }
    
    /**
     * @param object $entity
     * @return string
     */
    private function getEntityDescription($entity)
    {
        $entityString = null;
        if (method_exists($entity, '__toString'))
        {
            $entityString = (string)$entity;
        }
        else
        {
            $entityString = spl_object_hash($entity);
        }
        return sprintf('%s <%s>', $entityString, get_class($entity));
    }
    
    /**
     * @param array $expected
     * @param array $actual 
     */
    protected function assertEntityCollectionEquals(array $expected, array $actual)
    {
        $entityManager = $this->getEntityManager();
        $expected = array_map(function($entity) use ($entityManager)
        {
            return $entityManager->merge($entity);
        }, $expected);
        
        $actual = array_map(function($entity) use ($entityManager)
        {
            return $entityManager->merge($entity);
        }, $actual);
        
        $this->assertEquals($expected, $actual);

        // Cannot simply compare because comparison goes in to some infinite loop
        /*$idMap = function($entity)
        {
            return $entity->getId();
        };
        $expectedIds = array_map($idMap, $expected);
        $actualIds = array_map($idMap, $actual);
        sort($expectedIds);
        sort($actualIds);
        
        $this->assertEquals($expectedIds, $actualIds);*/
    }
    
    protected function tearDownDatabase()
    {
        $entityManager = $this->getEntityManager();
        $this->getOperationsHelper()->tearDownDatabase($entityManager);
    }
}