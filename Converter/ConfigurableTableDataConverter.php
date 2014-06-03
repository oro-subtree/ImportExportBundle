<?php

namespace Oro\Bundle\ImportExportBundle\Converter;

use Oro\Bundle\EntityBundle\Provider\EntityFieldProvider;
use Oro\Bundle\ImportExportBundle\Field\FieldHelper;
use Oro\Bundle\ImportExportBundle\Processor\EntityNameAwareInterface;
use Oro\Bundle\ImportExportBundle\Exception\LogicException;

class ConfigurableTableDataConverter extends AbstractTableDataConverter implements EntityNameAwareInterface
{
    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var EntityFieldProvider
     */
    protected $fieldProvider;

    /**
     * @var FieldHelper
     */
    protected $fieldHelper;

    /**
     * @param EntityFieldProvider $fieldProvider
     * @param FieldHelper $fieldHelper
     */
    public function __construct(EntityFieldProvider $fieldProvider, FieldHelper $fieldHelper)
    {
        $this->fieldProvider = $fieldProvider;
        $this->fieldHelper = $fieldHelper;
    }

    /**
     * {@inheritdoc}
     */
    protected function getHeaderConversionRules()
    {
        $this->assertEntityName();

        return $this->getEntityRules($this->entityName, true, 2, 1);
    }

    /**
     * {@inheritdoc}
     */
    protected function getBackendHeader()
    {
        $conversionRules = $this->receiveHeaderConversionRules();

        // extract all multiple relations
        $multipleRelations = array();
        foreach ($conversionRules as $code => $value) {
            if (is_array($value) && preg_match('~^(.+?):(.+?):(.+?)$~', $code, $matches)) {
                $entityName = $matches[1];
                $fieldName = $matches[2];
                $relatedFieldName = $matches[3];

                // leave one field at basic array
                if (!isset($multipleRelations[$entityName][$fieldName])) {
                    $multipleRelations[$entityName][$fieldName] = array($relatedFieldName => $value);
                } else {
                    $multipleRelations[$entityName][$fieldName][$relatedFieldName] = $value;
                    unset($conversionRules[$code]);
                }
            }
        }

        // calculate max number of related entities
        $maxRelations = array();
        foreach ($multipleRelations as $entityName => $relations) {
            foreach (array_keys($relations) as $fieldName) {
                $maxRelations[$entityName][$fieldName] = 5; // TODO Use max calculator as external service
            }
        }

        // build backend header
        $backendHeader = array();
        foreach ($conversionRules as $code => $value) {
            if (is_string($value)) {
                $backendHeader[] = $value;
            } elseif (is_array($value) && preg_match('~^(.+?):(.+?):(.+?)$~', $code, $matches)) {
                $entityName = $matches[1];
                $fieldName = $matches[2];
                if (!empty($multipleRelations[$entityName][$fieldName])
                    && !empty($maxRelations[$entityName][$fieldName])
                ) {
                    // add required amount of field groups
                    for ($i = 0; $i < $maxRelations[$entityName][$fieldName]; $i++) {
                        foreach ($multipleRelations[$entityName][$fieldName] as $fieldData) {
                            if (!empty($fieldData[self::BACKEND_TO_FRONTEND][0])) {
                                $backendHeader[] = str_replace('(\d+)', $i, $fieldData[self::BACKEND_TO_FRONTEND][0]);
                            }
                        }
                    }
                }
            }
        }

        return $backendHeader;
    }

    /**
     * {@inheritdoc}
     */
    public function setEntityName($entityName)
    {
        $this->entityName = $entityName;
    }

    /**
     * @throws LogicException
     */
    protected function assertEntityName()
    {
        if (!$this->entityName) {
            throw new LogicException('Entity class for data converter is not specified');
        }
    }

    /**
     * @param $entityName
     * @param bool $fullData
     * @param int $singleRelationDeepLevel
     * @param int $multipleRelationDeepLevel
     * @return array
     */
    protected function getEntityRules(
        $entityName,
        $fullData = false,
        $singleRelationDeepLevel = 0,
        $multipleRelationDeepLevel = 0
    ) {
        // get fields data
        $fields = $this->fieldProvider->getFields($entityName, true);

        // generate conversion rules
        $rules = array();
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            if ($this->fieldHelper->getConfigValue($entityName, $fieldName, 'excluded')) {
                continue;
            }

            $fieldHeader = $this->fieldHelper->getConfigValue($entityName, $fieldName, 'header', $field['label']);

            $fieldOrder = $this->fieldHelper->getConfigValue($entityName, $fieldName, 'order');
            if ($fieldOrder === null || $fieldOrder === '') {
                $fieldOrder = 10000;
            }
            $fieldOrder = (int)$fieldOrder;

            if ($this->fieldHelper->isRelation($field)) {
                $isSingleRelation = $this->fieldHelper->isSingleRelation($field) && $singleRelationDeepLevel > 0;
                $isMultipleRelation = $this->fieldHelper->isMultipleRelation($field) && $multipleRelationDeepLevel > 0;

                if ($fullData && ($isSingleRelation || $isMultipleRelation)) {
                    $relatedEntityName = $field['related_entity_name'];
                    $fieldFullData = $this->fieldHelper->getConfigValue($entityName, $fieldName, 'full', false);

                    $relationRules = $this->getEntityRules(
                        $relatedEntityName,
                        $fieldFullData,
                        $singleRelationDeepLevel - 1,
                        $multipleRelationDeepLevel - 1
                    );

                    $subOrder = 0;
                    foreach ($relationRules as $header => $name) {
                        // single relation
                        if ($isSingleRelation) {
                            $relationHeader = $fieldHeader . ' ' . $header;
                            $relationName = $fieldName . $this->convertDelimiter . $name;
                            $rules[$relationHeader] = array(
                                'name' => $relationName,
                                'order' => $fieldOrder,
                                'subOrder' => $subOrder++,
                            );
                        // multiple relation
                        } elseif ($isMultipleRelation) {
                            $relationCode = $entityName . ':' . $fieldName . ':' . $name;
                            $rules[$relationCode] = array(
                                'name' => array(
                                    self::FRONTEND_TO_BACKEND => array(
                                        $fieldHeader . ' (\d+) ' . $header,
                                        function (array $matches) use ($fieldName, $name) {
                                            return $fieldName . $this->convertDelimiter . ($matches[1] - 1)
                                                . $this->convertDelimiter . $name;
                                        }
                                    ),
                                    self::BACKEND_TO_FRONTEND => array(
                                        $fieldName . $this->convertDelimiter . '(\d+)'
                                        . $this->convertDelimiter . $name,
                                        function (array $matches) use ($fieldHeader, $header) {
                                            return $fieldHeader . ' ' . ($matches[1] + 1) . ' ' . $header;
                                        }
                                    ),
                                ),
                                'order' => $fieldOrder,
                                'subOrder' => $subOrder++,
                            );
                        }
                    }
                }
            } else {
                if ($fullData || $this->fieldHelper->getConfigValue($entityName, $fieldName, 'identity')) {
                    $rules[$fieldHeader] = array('name' => $fieldName, 'order' => $fieldOrder);
                }
            }
        }

        return $this->sortRules($rules);
    }

    /**
     * @param int $a
     * @param int $b
     * @return int
     */
    protected function sortRulesCallback($a, $b)
    {
        if ($a['order'] > $b['order']) {
            return 1;
        } elseif ($a['order'] < $b['order']) {
            return -1;
        } else {
            $aSub = isset($a['subOrder']) ? $a['subOrder'] : 0;
            $bSub = isset($b['subOrder']) ? $b['subOrder'] : 0;
            return $aSub > $bSub ? 1 : -1;
        }
    }

    /**
     * Uses key "order" to sort rules
     *
     * @param array $rules
     * @return array
     */
    protected function sortRules(array $rules)
    {
        // sort fields by order
        uasort($rules, array($this, 'sortRulesCallback'));

        // clear unused data
        foreach ($rules as $label => $data) {
            $rules[$label] = $data['name'];
        }

        return $rules;
    }
}
