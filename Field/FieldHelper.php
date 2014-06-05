<?php

namespace Oro\Bundle\ImportExportBundle\Field;

use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface;

class FieldHelper
{
    /**
     * @var ConfigProviderInterface
     */
    protected $configProvider;

    /**
     * @param ConfigProviderInterface $configProvider
     */
    public function __construct(ConfigProviderInterface $configProvider)
    {
        $this->configProvider = $configProvider;
    }

    /**
     * @param string $entityName
     * @param string $fieldName
     * @param string $parameter
     * @param mixed $default
     * @return mixed|null
     */
    public function getConfigValue($entityName, $fieldName, $parameter, $default = null)
    {
        if (!$this->configProvider->hasConfig($entityName, $fieldName)) {
            return $default;
        }

        $fieldConfig = $this->configProvider->getConfig($entityName, $fieldName);
        if (!$fieldConfig->has($parameter)) {
            return $default;
        }

        return $fieldConfig->get($parameter);
    }

    /**
     * @param string $className
     * @param null|string $fieldName
     * @return bool
     */
    public function hasConfig($className, $fieldName = null)
    {
        return $this->configProvider->hasConfig($className, $fieldName);
    }

    /**
     * @param array $field
     * @return bool
     */
    public function isRelation(array $field)
    {
        return !empty($field['relation_type']) && !empty($field['related_entity_name']);
    }

    /**
     * @param array $field
     * @return bool
     */
    public function isSingleRelation(array $field)
    {
        return $this->isRelation($field)
            && in_array($field['relation_type'], array('ref-one', 'oneToOne', 'manyToOne'));
    }

    /**
     * @param array $field
     * @return bool
     */
    public function isMultipleRelation(array $field)
    {
        return $this->isRelation($field)
            && in_array($field['relation_type'], array('ref-many', 'oneToMany', 'manyToMany'));
    }
}