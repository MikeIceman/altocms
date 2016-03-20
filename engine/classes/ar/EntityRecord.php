<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

namespace alto\engine\ar;
use \E as E, \F as F, \C as C;

/**
 * Абстрактный класс сущности ORM - аналог Active Record
 *
 * @package engine.ar
 * @since   1.2
 */
abstract class EntityRecord extends \Entity {

    const ATTR_IS_PROP     = 1;
    const ATTR_IS_FIELD    = 2;
    const ATTR_IS_CALLABLE = 3;
    const ATTR_IS_RELATION = 4;

    protected $aExtra;

    protected $sPrimaryKey;

    protected $sTableName;

    /**
     * Список полей таблицы сущности
     *
     * @var array
     */
    protected $aTableColumns = null;

    protected $aAttributes = [];

    static protected $oInstance;

    /**
     * Установка связей
     * @see \Entity::__construct
     *
     * @param bool $aParams Ассоциативный массив данных сущности
     */
    public function __construct($aParams = null) {

        parent::__construct($aParams);
    }

    /**
     * @return array
     */
    public function __sleep() {

        foreach($this->_aData as $sKey => $xVal) {
            if (0 === strpos($sKey, '__')) {
                unset($this->_aData[$sKey]);
            }
        }
        $aProperties = get_class_vars(get_called_class());
        $aProperties = array_keys($aProperties);

        return $aProperties;
    }

    /**
     * @return EntityRecord
     */
    static public function instance() {

        if (!self::$oInstance) {
            self::$oInstance = E::GetEntity(get_called_class());
        }
        return self::$oInstance;
    }

    /**
     * @return string
     */
    static public function tableName() {

        return self::instance()->getTableName();
    }

    /* *** Extra data *** */

    /**
     * @param array $aExtra
     *
     * @return string
     */
    protected function extraSerialize($aExtra) {

        $aExtra = (array)$aExtra;
        return 'j:' . json_encode($aExtra);
    }

    /**
     * @param string $sExtra
     *
     * @return array
     */
    protected function extraUnserialize($sExtra) {

        $aExtra = [];
        if ($sExtra) {
            if (0 === strpos($sExtra, 'j:')) {
                $aExtra = @json_decode($sExtra, true);
            } else {
                $aExtra = @unserialize($sExtra);
            }
            $aExtra = (array)$aExtra;
        }
        return $aExtra;
    }

    /**
     * @param string $sKey
     * @param mixed  $xVal
     *
     * @return EntityRecord
     */
    public function setPropExtra($sKey, $xVal) {

        if (is_null($this->aExtra)) {
            $this->aExtra = $this->extraUnserialize($this->getAttr('extra'));
        }
        $this->aExtra[$sKey] = $xVal;

        return $this;
    }

    /**
     * @param string $sKey
     *
     * @return null|mixed
     */
    public function getPropExtra($sKey) {

        if (is_null($this->aExtra)) {
            $this->aExtra = $this->extraUnserialize($this->getAttr('extra'));
        }
        if (isset($this->aExtra[$sKey])) {
            return $this->aExtra[$sKey];
        }
        return null;
    }

    /**
     * @return mixed|null
     */
    public function getExtra() {

        if (is_null($this->aExtra)) {
            return $this->getProp('extra');
        }
        return $this->extraSerialize($this->aExtra);
    }

    /**
     * @param $sExtra
     *
     * @return EntityRecord
     */
    public function setExtra($sExtra) {

        $this->setProp('extra', $sExtra);
        return $this;
    }

    /* *** --- *** */

    public function clearProps() {

        foreach($this->_aData as $sKey => $xVal) {
            if (0 === strpos($sKey, '__')) {
                unset($this->_aData[$sKey]);
            }
        }
    }

    /**
     * @return string
     */
    public function getModuleClass() {

        $sModuleClass = $this->getProp('__module_class');
        if (!$sModuleClass) {
            $aInfo = E::GetClassInfo($this, E::CI_MODULE | E::CI_PPREFIX);

            $sModuleClass = E::ModulePlugin()->GetDelegate('module', $aInfo[E::CI_MODULE]);
            if ($sModuleClass == $aInfo[E::CI_MODULE] && !empty($aInfo[E::CI_PPREFIX])) {
                $sPluginModuleClass = $aInfo[E::CI_PPREFIX] . 'Module' . $sModuleClass;
                if (class_exists($sPluginModuleClass, false)) {
                    // class like "PluginTest_ModuleTest" has no delegates
                    $sModuleClass = $sPluginModuleClass;
                }
            }
            $this->setProp('__module_class', $sModuleClass);
        }
        return $sModuleClass;
    }

    /**
     * @return ArModule
     */
    public function getModule() {

        $oModule = $this->getProp('__module');
        if (!$oModule) {
            $sModuleClass = $this->getModuleClass();
            $oModule = E::Module(str_replace('_', '\\', $sModuleClass));
            $this->setProp('__module', $oModule);
        }
        return $oModule;
    }

    /**
     * @param ArModule $xModule
     */
    public function setModule($xModule) {

        if (is_object($xModule)) {
            $this->setProp('__module', $xModule);
            $this->setProp('__module_class', get_class($xModule));
        } else {
            $this->setProp('__module', null);
            $this->setProp('__module_class', $xModule);
        }
    }

    /**
     * @return ArMapper
     */
    public function getMapper() {

        if ($oModule = $this->getModule()) {
            return $oModule->getMapper();
        }
        return null;
    }

    /**
     * @return string
     */
    public function getTableName() {

        if (empty($this->sTableName)) {
            $sClass = E::ModulePlugin()->GetDelegater('entity', get_called_class());
            $sModuleName = F::StrUnderscore(E::GetModuleName($sClass));
            $sEntityName = F::StrUnderscore(E::GetEntityName($sClass));
            if (strpos($sEntityName, $sModuleName) === 0) {
                $sTable = F::StrUnderscore($sEntityName);
            } else {
                $sTable = F::StrUnderscore($sModuleName) . '_' . F::StrUnderscore($sEntityName);
            }

            // * Если название таблиц переопределено в конфиге, то возвращаем его
            if (C::Get('db.table.' . $sTable)) {
                $this->sTableName = C::Get('db.table.' . $sTable);
            } else {
                $this->sTableName = C::Get('db.table.prefix') . $sTable;
            }
        } elseif (substr($this->sTableName, 0, 2) === '?_') {
            return C::Get('db.table.prefix') . substr($this->sTableName, 2);
        }
        return $this->sTableName;
    }

    /**
     * Получение primary key из схемы таблицы
     *
     * @return string|array    Если индекс составной, то возвращает массив полей
     */
    public function getPrimaryKey() {

        if (!$this->sPrimaryKey) {
            /** @var array $aIndex */
            $aIndex = $this->getModule()->getMapper()->readPrimaryIndexFromTable($this->getTableName());
            if (is_array($aIndex)) {
                if (count($aIndex) > 1) {
                    // Составной индекс
                    $this->sPrimaryKey = $aIndex;
                } else {
                    $this->sPrimaryKey = $aIndex[1];
                }
            }
        }
        return $this->sPrimaryKey;
    }

    /**
     * Получение значения primary key
     *
     * @return string
     */
    public function getPrimaryKeyValue() {

        return $this->getProp($this->_getPrimaryKey());
    }

    /**
     * @param null $aKeys
     *
     * @return array
     */
    public function getAllProps($aKeys = null) {

        $aProps = parent::getAllProps($aKeys);
        if (is_null($aKeys)) {
            foreach($aProps as $sKey => $xVal) {
                if (substr($sKey, 0, 2) == '__') {
                    unset($aProps[$sKey]);
                }
            }
        }
        return $aProps;
    }

    /**
     * @param EntityCollection $oCollection
     *
     * @return EntityRecord
     */
    public function setCollection($oCollection) {

        $this->setProp('__collection', $oCollection);
        return $this;
    }

    /**
     * @return EntityCollection|null
     */
    public function getCollection() {

        return $this->getProp('__collection');
    }

    /**
     * @param $oBuilder
     *
     * @return Builder
     */
    public function find($oBuilder) {

        return $oBuilder;
    }

    /**
     * Сохранение сущности в БД (если новая то создается)
     *
     * @return \Entity|false
     */
    public function save() {

        if ($this->beforeSave()) {
            if ($res = $this->_callMethod(__FUNCTION__)) {
                $this->afterSave();
                return $res;
            }
        }
        return false;
    }

    /**
     * Удаление сущности из БД
     *
     * @return \Entity|false
     */
    public function delete() {

        if ($this->beforeDelete()) {
            if ($res = $this->_callMethod(__FUNCTION__)) {
                $this->afterDelete();
                return $res;
            }
        }
        return false;
    }

    /**
     * Обновляет данные сущности из БД
     *
     * @return \Entity|false
     */
    public function reload() {

        return $this->_callMethod(__FUNCTION__);
    }

    /**
     * Хук, срабатывает перед сохранением сущности
     *
     * @return bool
     */
    protected function beforeSave() {

        return true;
    }

    /**
     * Хук, срабатывает после сохранением сущности
     *
     */
    protected function afterSave() {

    }

    /**
     * Хук, срабатывает перед удалением сущности
     *
     * @return bool
     */
    protected function beforeDelete() {

        return true;
    }

    /**
     * Хук, срабатывает после удаления сущности
     *
     */
    protected function afterDelete() {

    }

    /**
     * Возвращает список полей сущности
     *
     * @return array
     */
    public function readColumns() {

        $oMapper = $this->getMapper();
        if ($oMapper) {
            $aColumns = $oMapper->readColumnsFromTable($this->getTableName());
        } else {
            $aColumns = [];
        }
        
        return $aColumns;
    }

    /**
     * @return ArModule
     */
    static public function model() {

        $sClass = get_called_class();
        $aClassInfoPrim = E::GetClassInfo($sClass, E::CI_MODULE | E::CI_PPREFIX | E::CI_PLUGIN);
        $sModuleName = (!empty($aClassInfoPrim[E::CI_MODULE]) ? $aClassInfoPrim[E::CI_MODULE] : null);
        $sPluginPrefix = (!empty($aClassInfoPrim[E::CI_PPREFIX]) ? $aClassInfoPrim[E::CI_PPREFIX] : null);
        $sPluginName = (!empty($aClassInfoPrim[E::CI_PLUGIN]) ? $aClassInfoPrim[E::CI_PLUGIN] : null);

        // * If Module not exists, try to find its root Delegator
        $aClassInfo = E::GetClassInfo($sPluginPrefix . 'Module_' . $sModuleName, E::CI_MODULE);
        if (empty($aClassInfo[E::CI_MODULE])) {
            $sRootDelegator = E::ModulePlugin()->GetRootDelegater('entity', $sClass);
            if ($sRootDelegator) {
                $sModuleName = E::GetModuleName($sRootDelegator);
                $sPluginName = E::GetPluginName($sRootDelegator);
            }
        }
        if ($sPluginName) {
            $sModuleName = 'Plugin' . $sPluginName . '\\' . $sModuleName;
        }

        return E::Module($sModuleName);
    }

    /**
     * @return ArMapper
     */
    static public function mapper() {

        return static::model()->getMapper();
    }

    /**
     * Проксирует вызов методов в модуль сущности
     *
     * @param string $sName    Название метода
     *
     * @return mixed
     */
    protected function _callMethod($sName) {

        $sModuleName = E::GetModuleName($this);
        $sEntityName = E::GetEntityName($this);
        $sPluginPrefix = E::GetPluginPrefix($this);
        $sPluginName = E::GetPluginName($this);

        // * If Module not exists, try to find its root Delegator
        $aClassInfo = E::GetClassInfo($sPluginPrefix . 'Module_' . $sModuleName, E::CI_MODULE);
        if (empty($aClassInfo[E::CI_MODULE])) {
            $sRootDelegator = E::ModulePlugin()->GetRootDelegater('entity', get_class($this));
            if ($sRootDelegator) {
                $sModuleName = E::GetModuleName($sRootDelegator);
                $sPluginPrefix = E::GetPluginPrefix($sRootDelegator);
                $sPluginName = E::GetPluginName($sRootDelegator);
            }
        }

        if ($sPluginName) {
            $sModuleName = 'Plugin' . $sPluginName . '\\' . $sModuleName;
        }
        $sMethodName = $sName . $sEntityName;

        return E::Module($sModuleName)->$sMethodName($this);
    }

    /**
     * @param string $sName
     * @param string $sTableField
     * 
     * @return EntityRecord
     */
    public function addAttr($sName, $sTableField) {

        $this->aAttributes[$sName] = [
            'type' => self::ATTR_IS_FIELD,
            'data' => $sTableField,
        ];

        return $this;
    }

    /**
     * Calculate and return value of attribute
     *
     * @param $sAttrName
     * @param $aAttrData
     *
     * @return mixed|null
     */
    protected function _getAttrValue($sAttrName, $aAttrData) {

        $iType = (isset($aAttrData['type']) ? $aAttrData['type'] : 0);
        $xData = (isset($aAttrData['data']) ? $aAttrData['data'] : null);
        if ($iType && $xData) {
            switch ($iType) {
                case self::ATTR_IS_PROP:
                    return $this->getProp($sAttrName);
                case self::ATTR_IS_FIELD:
                    return $this->getProp($xData);
                case self::ATTR_IS_CALLABLE:
                    return call_user_func($xData, $sAttrName, $this);
                case self::ATTR_IS_RELATION:
                    if ($this->hasRelBind($sAttrName)) {
                        return $this->getRelBind($sAttrName);
                    }
                    /** @var $xData Relation */
                    return $xData->getResult($this);
            }
        }
        return null;
    }

    /**
     * Return array of attribute data by type
     *
     * @param int $iType
     *
     * @return array
     */
    protected function _getAttrDataByType($iType) {

        $aResult = [];
        if ($this->aAttributes) {
            foreach($this->aAttributes as $sAttrName => $aAttrData) {
                if (isset($aAttrData['type']) && $aAttrData['type'] === $iType) {
                    $aResult[$sAttrName] = $aAttrData;
                }
            }
        }
        return $aResult;
    }

    /**
     * Return value of the attribute
     *
     * @param $sName
     *
     * @return mixed|null
     */
    public function getAttr($sName) {

        if (strpos($sName, '.')) {
            list($sAttrName, $sLastName) = explode('.', $sName, 2);
            $oAttr = $this->getAttr($sAttrName);
            if (is_object($oAttr) && $oAttr instanceof EntityRecord) {
                return $oAttr->getAttr($sLastName);
            }
        } elseif (isset($this->aAttributes[$sName])) {
            return $this->_getAttrValue($sName, $this->aAttributes[$sName]);
        }
        return $this->getProp($sName);
    }


    /**
     * Return values of all attributes
     *
     * @param null $aNames
     *
     * @return array
     */
    public function getAttributes($aNames = null) {

        $aResult = $this->getAllProps($aNames);
        if ($this->aAttributes) {
            if (!is_array($aNames)) {
                $aNames = (array)$aNames;
            }
            foreach($this->aAttributes as $sAttrName => $aAttrData) {
                if (empty($aNames) || in_array($sAttrName, $aNames)) {
                    $aResult[$sAttrName] = $this->_getAttrValue($sAttrName, $aAttrData);
                }
            }
        }
        return $aResult;
    }

    public function getAttribute($sName) {

    }

    public function setAttribute($sName, $xValue) {

        return $this;
    }

    /**
     * Возвращает список полей сущности
     *
     * @return array
     */
    public function getTableColumns() {

        $aColumns = $this->getProp('__columns');
        if (is_null($aColumns)) {
            if (is_null($this->aTableColumns)) {
                $this->aTableColumns = $this->readColumns();
            }
            if (!empty($this->aFields)) {
                $aColumns = array_merge($this->aTableColumns, $this->aFields);
            } else {
                $aColumns = $this->aTableColumns;
            }
            $this->setProp('__columns', $aColumns);
        }

        return $aColumns;
    }

    /**
     * @return array
     */
    public function getFields() {
        
        $aFields = $this->getTableColumns();
        $aFieldAliases = $this->_getAttrDataByType(self::ATTR_IS_FIELD);
        foreach ($aFieldAliases as $sName => $aAttr) {
            if (!empty($aAttr['data']) && is_string($aAttr['data']) && isset($aFields[$aAttr['data']])) {
                $aFields[$sName] = $aFields[$aAttr['data']];
            }
        }
        return $aFields;
    }
    
    /**
     * @param string $sField Название поля
     *
     * @return null|string
     */
    public function getFieldName($sField) {

        if ($aFields = $this->getFieldsInfo()) {
            $sFieldKey = strtolower($sField);
            if (isset($aFields[$sFieldKey])) {
                if (isset($aFields[$sFieldKey]['field'])) {
                    return $aFields[$sFieldKey]['field'];
                }
                return $sField;
            }
        }
        return $sField;
    }

    /**
     * Add relation
     * 
     * @param string $sRelType
     * @param string $sField
     * @param string $sRelEntity
     * @param array $aRelFields
     *
     * @return Relation
     */
    public function addRelation($sRelType, $sField, $sRelEntity, $aRelFields) {

        $oRelation = new Relation($sRelType, $this, $sField, $sRelEntity, $aRelFields);
        $this->aAttributes[$sField] = [
            'type' => self::ATTR_IS_RELATION,
            'data' => $oRelation,
        ];

        return $oRelation;
    }

    /**
     * Add relation one-to-one
     * 
     * @param array $aRelation
     * @param array $aRelFields
     *
     * @return Relation
     */
    public function addRelOne($aRelation, $aRelFields = null) {

        $sRelEntity = reset($aRelation);
        $sField = key($aRelation);

        return $this->addRelation(ArModule::RELATION_HAS_ONE, $sField, $sRelEntity, $aRelFields);
    }

    /**
     * Add relation one-to-many
     * 
     * @param array $aRelation
     * @param array $aRelFields
     *
     * @return Relation
     */
    public function addRelMany($aRelation, $aRelFields = null) {

        $sRelEntity = reset($aRelation);
        $sField = key($aRelation);

        return $this->addRelation(ArModule::RELATION_HAS_MANY, $sField, $sRelEntity, $aRelFields);
    }

    /**
     * Add relation many-to-many via junction table
     * 
     * @param array $aRelation
     * @param string $sJuncTable
     * @param null $xJuncToRelation
     * @param null $xJuncToMaster
     *
     * @return Relation
     */
    public function addRelManyVia($aRelation, $sJuncTable, $xJuncToRelation = null, $xJuncToMaster = null) {

        $sRelEntity = reset($aRelation);
        $sField = key($aRelation);

        if (is_array($xJuncToRelation)) {
            $sRelKey = reset($xJuncToRelation);
            $sJuncRelKey = key($xJuncToRelation);
        } else {
            $sRelKey = $sJuncRelKey = $xJuncToRelation;
        }
        if (is_array($xJuncToMaster)) {
            $sMasterKey = reset($xJuncToMaster);
            $sJuncMasterKey = key($xJuncToMaster);
        } else {
            $sMasterKey = $sJuncMasterKey = $xJuncToMaster;
        }

        return $this
            ->addRelation(ArModule::RELATION_HAS_MANY, $sField, $sRelEntity, array($sRelKey => $sMasterKey))
            ->viaTable($sJuncTable, $sJuncRelKey, $sJuncMasterKey);
    }

    /**
     * Add relation with aggregate function
     *
     * @param string $sField
     * @param string $sRelEntity
     * @param array  $aRelFields
     *
     * @return Relation
     */
    public function addRelStat($sField, $sRelEntity, $aRelFields = null) {

        if (is_array($sField) && (is_array($sRelEntity))) {
            $aRelFields = $sRelEntity;
            $sRelEntity = reset($sField);
            $sField = key($sField);
        }
        return $this->addRelation(ArModule::RELATION_HAS_STAT, $sField, $sRelEntity, $aRelFields);
    }

    /**
     * Return all relations
     *
     * @return array
     */
    public function getRelations() {

        return $this->_getAttrDataByType(self::ATTR_IS_RELATION);
    }

    public function getRelation($sName) {
        
        $aRelations = $this->getRelations();
        if (isset($aRelations[$sName])) {
            return $aRelations[$sName];
        }
        return null;
    }

    /**
     * Bind result data to relation
     * 
     * @param string $sName
     * @param mixed  $xData
     */
    public function setRelBind($sName, $xData) {
        
        if (isset($this->aAttributes[$sName]['type']) && $this->aAttributes[$sName]['type'] == self::ATTR_IS_RELATION) {
            $this->aAttributes[$sName]['bind'] = $xData;
        }
    }

    /**
     * Return binding data if exists
     * 
     * @param string $sName
     *
     * @return null
     */
    public function getRelBind($sName) {

        if ($this->hasRelBind($sName)) {
            return $this->aAttributes[$sName]['bind'];
        }
        return null;
    }

    /**
     * Check binding data
     * 
     * @param string $sName
     *
     * @return bool
     */
    public function hasRelBind($sName) {

        if (isset($this->aAttributes[$sName]['type']) && $this->aAttributes[$sName]['type'] == self::ATTR_IS_RELATION) {
            return array_key_exists('bind', $this->aAttributes[$sName]);
        }
        return false;
    }
    
    /**
     * @param string $sName
     *
     * @return int
     */
    public function hasAttribute($sName) {

        if ($this->hasProp($sName)) {
            return self::ATTR_IS_PROP;
        }

        $sField = $this->getFieldName($sName);
        if (is_scalar($sField) && $sField != $sName) {
            return self::ATTR_IS_FIELD;
        }
        if (is_callable($sField)) {
            return self::ATTR_IS_CALLABLE;
        }

        if (!empty($this->aRelationsData[$sName])) {
            return self::ATTR_IS_RELATION;
        }

        return 0;
    }

    /**
     * @param string|array $xKey
     *
     * @return mixed|null
     */
    public function getFieldValue($xKey) {

        if (is_array($xKey)) {
            $aResult = [];
            foreach($xKey as $sKey) {
                $aResult[$sKey] = $this->getAttr($sKey);
            }
            return $aResult;
        }
        $sKey = (string)$xKey;
        $iFlag = $this->hasAttribute($sKey);
        if ($iFlag == self::ATTR_IS_PROP) {
            return $this->getProp($sKey);
        } elseif ($iFlag == self::ATTR_IS_FIELD) {
            $sField = $this->getFieldName($sKey);
            if ($sField != $sKey && $this->hasProp($sField)) {
                return $this->getProp($sField);
            }
        } elseif ($iFlag == self::ATTR_IS_CALLABLE) {
            $xCallback = $this->getFieldName($sKey);
            return $xCallback($this);
        } elseif ($iFlag == self::ATTR_IS_RELATION) {
            $sField = $this->getFieldName($sKey);

            /** @var Relation $oRelation */
            $oRelation = $this->aRelationsData[$sKey];
            $xValue = $oRelation->getResult();

            $this->setProp($sField, $xValue);
            return $xValue;
        }
        return null;
    }

    public function __clone() {

        $this->clearProps();
        if (!empty($this->aRelationsData)) {
            foreach($this->aRelationsData as $sKey => $oRelation) {
                $this->aRelationsData[$sKey] = clone $oRelation;
                $this->aRelationsData[$sKey]->setMasterEntity($this);
            }
        }
    }

    /**
     * Ставим хук на вызов неизвестного метода и считаем что хотели вызвать метод какого либо модуля
     * Также производит обработку методов set* и get*
     * Учитывает связи и может возвращать связанные данные
     *
     * @see Engine::_CallModule
     *
     * @param string $sName Имя метода
     * @param array  $aArgs Аргументы
     *
     * @return mixed
     */
    public function __call($sName, $aArgs) {

        $sType = substr($sName, 0, strpos(F::StrUnderscore($sName), '_'));
        if (!strpos($sName, '_') && in_array($sType, array('get', 'set', 'reload'))) {
            $sKey = F::StrUnderscore(preg_replace('/' . $sType . '/', '', $sName, 1));
            if ($sType == 'get') {
                return $this->getAttr($sKey);
            } elseif ($sType == 'set' && array_key_exists(0, $aArgs)) {
                if (array_key_exists($sKey, $this->aRelationsData)) {
                    $this->aRelationsData[$sKey] = $aArgs[0];
                } else {
                    return $this->setProp($sKey, $aArgs[0]);
                }
            } elseif ($sType == 'reload') {
                if (array_key_exists($sKey, $this->aRelationsData)) {
                    unset($this->aRelationsData[$sKey]);
                    return $this->__call('get' . F::StrCamelize($sKey), $aArgs);
                }
            }
        }
        return parent::__call($sName, $aArgs);
    }

}

// EOF