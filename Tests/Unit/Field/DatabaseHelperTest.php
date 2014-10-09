<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\File;

use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\ImportExportBundle\Field\DatabaseHelper;

class DatabaseHelperTest extends \PHPUnit_Framework_TestCase
{
    const TEST_CLASS = 'stdClass';

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityManager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $repository;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $metadata;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $doctrineHelper;

    /**
     * @var DatabaseHelper
     */
    protected $helper;

    protected function setUp()
    {
        $this->metadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $this->entityManager = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->entityManager->expects($this->any())
            ->method('getClassMetadata')
            ->with(self::TEST_CLASS)
            ->will($this->returnValue($this->metadata));

        $this->repository = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();

        $registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $registry->expects($this->any())
            ->method('getManagerForClass')
            ->with(self::TEST_CLASS)
            ->will($this->returnValue($this->entityManager));
        $registry->expects($this->any())
            ->method('getRepository')
            ->with(self::TEST_CLASS)
            ->will($this->returnValue($this->repository));

        $this->doctrineHelper = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\DoctrineHelper')
            ->disableOriginalConstructor()
            ->getMock();

        $fieldHelper = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink')
            ->disableOriginalConstructor()
            ->getMock();

        $this->helper = new DatabaseHelper($registry, $this->doctrineHelper, $fieldHelper);
    }

    public function testFind()
    {
        $entity = new \stdClass();
        $identifier = 1;

        $this->doctrineHelper->expects($this->once())
            ->method('getEntity')
            ->with(self::TEST_CLASS, $identifier)
            ->will($this->returnValue($entity));

        $this->assertEquals($entity, $this->helper->find(self::TEST_CLASS, $identifier));
    }

    public function testGetIdentifier()
    {
        $entity = new \stdClass();
        $identifier = 1;

        $this->doctrineHelper->expects($this->once())
            ->method('getSingleEntityIdentifier')
            ->with($entity)
            ->will($this->returnValue($identifier));

        $this->assertEquals($identifier, $this->helper->getIdentifier($entity));
    }

    public function testGetIdentifierFieldName()
    {
        $fieldName = 'id';

        $this->doctrineHelper->expects($this->once())
            ->method('getSingleEntityIdentifierFieldName')
            ->with(self::TEST_CLASS)
            ->will($this->returnValue($fieldName));

        $this->assertEquals($fieldName, $this->helper->getIdentifierFieldName(self::TEST_CLASS));
    }

    /**
     * @param array $mapping
     * @param bool $isCascade
     * @dataProvider isCascadePersistDataProvider
     */
    public function testIsCascadePersist(array $mapping, $isCascade)
    {
        $fieldName = 'relation';

        $this->metadata->expects($this->once())
            ->method('getAssociationMapping')
            ->with($fieldName)
            ->will($this->returnValue($mapping));

        $this->assertEquals($isCascade, $this->helper->isCascadePersist(self::TEST_CLASS, $fieldName));
    }

    /**
     * @return array
     */
    public function isCascadePersistDataProvider()
    {
        return array(
            'no cascade operations' => [
                'mapping'   => [],
                'isCascade' => false,
            ],
            'no cascade persist' => [
                'mapping'   => ['cascade' => ['remove']],
                'isCascade' => false,
            ],
            'cascade persist' => [
                'mapping'   => ['cascade' => ['persist']],
                'isCascade' => true,
            ],
        );
    }

    public function testResetIdentifier()
    {
        $entity = new \stdClass();
        $fieldName = 'id';

        $this->doctrineHelper->expects($this->once())
            ->method('getSingleEntityIdentifierFieldName')
            ->with(self::TEST_CLASS)
            ->will($this->returnValue($fieldName));

        $this->metadata->expects($this->once())
            ->method('setIdentifierValues')
            ->with($entity, [$fieldName => null])
            ->will($this->returnValue($fieldName));

        $this->helper->resetIdentifier($entity);
    }

    /**
     * @param array $association
     * @param string $expectedField
     * @dataProvider getInversedRelationFieldNameDataProvider
     */
    public function testGetInversedRelationFieldName(array $association, $expectedField)
    {
        $fieldName = 'relation';

        $this->metadata->expects($this->once())
            ->method('getAssociationMapping')
            ->with($fieldName)
            ->will($this->returnValue($association));

        $this->assertEquals($expectedField, $this->helper->getInversedRelationFieldName(self::TEST_CLASS, $fieldName));
    }

    /**
     * @return array
     */
    public function getInversedRelationFieldNameDataProvider()
    {
        return array(
            'mapped by field' => array(
                'association' => array('mappedBy' => 'field'),
                'expectedField' => 'field',
            ),
            'inversed by field' => array(
                'association' => array('inversedBy' => 'field'),
                'expectedField' => 'field',
            ),
            'no inversed field' => array(
                'association' => array(),
                'expectedField' => null,
            ),
        );
    }

    /**
     * @param string $type
     * @param bool $expected
     * @dataProvider isSingleInversedRelationDataProvider
     */
    public function testIsSingleInversedRelation($type, $expected)
    {
        $fieldName = 'relation';

        $this->metadata->expects($this->once())
            ->method('getAssociationMapping')
            ->with($fieldName)
            ->will($this->returnValue(array('type' => $type)));

        $this->assertEquals($expected, $this->helper->isSingleInversedRelation(self::TEST_CLASS, $fieldName));
    }

    /**
     * @return array
     */
    public function isSingleInversedRelationDataProvider()
    {
        return array(
            'one to one'   => array(ClassMetadata::ONE_TO_ONE, true),
            'one to many'  => array(ClassMetadata::ONE_TO_MANY, true),
            'many to one'  => array(ClassMetadata::MANY_TO_ONE, false),
            'many to many' => array(ClassMetadata::MANY_TO_MANY, false),
        );
    }
}
