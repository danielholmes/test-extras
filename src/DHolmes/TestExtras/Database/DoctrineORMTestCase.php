<?php

namespace DHolmes\TestExtras\Database;

use PHPUnit_Framework_TestCase;
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
        return $this->getEntityManager()->merge($entity);
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
        
        $this->assertSame($expectedEntity, $entity, 
            sprintf('Entities not the same (%s) (%s)', get_class($expectedEntity), get_class($entity)));
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