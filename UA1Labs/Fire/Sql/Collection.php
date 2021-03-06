<?php

/**
 *    __  _____   ___   __          __
 *   / / / /   | <  /  / /   ____ _/ /_  _____
 *  / / / / /| | / /  / /   / __ `/ __ `/ ___/
 * / /_/ / ___ |/ /  / /___/ /_/ / /_/ (__  )
 * `____/_/  |_/_/  /_____/`__,_/_.___/____/
 *
 * @package FireSql
 * @author UA1 Labs Developers https://ua1.us
 * @copyright Copyright (c) UA1 Labs
 */

namespace UA1Labs\Fire\Sql;

use \DateTime;
use \UA1Labs\Fire\Sql\Statement;
use \UA1Labs\Fire\Sql\Filter;
use \UA1Labs\Fire\Sql\Connector;
use \UA1Labs\Fire\SqlException;

/**
 * The class that represents a collection. With the functionality built into this class
 * you'll be able to create, read, update, and delete objects from within the collection.
 */
class Collection
{

    /**
     * The connection to the database.
     *
     * @var \UA1Labs\Fire\Sql\Connector
     */
    private $connector;

    /**
     * The name of the collection.
     *
     * @var string
     */
    private $name;

    /**
     * Options used to configure how a collection should work.
     *
     * @var object
     */
    private $options;

    /**
     * Creates an instance of a new collection.
     *
     * Default $options:
     * versionTracking | false | Determines if object updates should maintain the history.
     * model | null | Provides the type of objects FireSql should be returning. If null FireSql will map objects to stdClass.
     *
     * @param string $name The name of the collection
     * @param \UA1Labs\Fire\Sql\Connector $pdo The connection to the database
     * @param array $options An array of options
     */
    public function __construct($name, Connector $connector, $options = null)
    {
        $this->connector = $connector;
        $this->name = $name;

        $defaultOptions = [
            'versionTracking' => false,
            'model' => null
        ];

        if ($options) {
            $opts = [];
            foreach ($defaultOptions as $option => $value) {
                $opts[$option] = isset($options[$option]) ? $options[$option] : $defaultOptions[$option];
            }
            $this->options = (object) $opts;
        } else {
            $this->options = (object) $defaultOptions;
        }
        
        $this->validateOptions();

        $createTables = Statement::get('CREATE_DB_TABLES', [
            '@collection' => $this->name
        ]);
        $this->connector->exec($createTables);
    }

    /**
     * Returns a collection of objects that match the filter criteria.
     *
     * @param string | null | \UA1Labs\Fire\Sql\Filter $filter
     * @return array
     */
    public function find($filter = null)
    {
        if (is_string($filter)) {
            if ($this->isValidJson($filter)) {
                $filter = new Filter($filter);
                return $this->getObjectsByFilter($filter);
            } else {
                return $this->getObject($filter);
            }
        } else if (is_object($filter) && $filter instanceof Filter) {
            return $this->getObjectsByFilter($filter);
        }
        return [];
    }

    /**
     * Inserts an object in the collection.
     *
     * @param object $object
     */
    public function insert($object)
    {
        return $this->upsert($object, null);
    }

    /**
     * Updates and object in the collection.
     *
     * @param string $id
     * @param object $object
     */
    public function update($id, $object)
    {
        return $this->upsert($object, $id);
    }

    /**
     * Deletes an object from the database.
     *
     * @param string $id The ID of the object you want to delete
     */
    public function delete($id)
    {
        $delete = Statement::get(
            'DELETE_OBJECT_INDEX',
            [
                '@collection' => $this->name,
                '@id' => $this->connector->quote($id)
            ]
        );
        $delete .= Statement::get(
            'DELETE_OBJECT',
            [
                '@collection' => $this->name,
                '@id' => $this->connector->quote($id)
            ]
        );

        $this->connector->exec($delete);
    }

    /**
     * Returns the total number of objects in a collection.
     *
     * @param string | null | \UA1Labs\Fire\Sql\Filter $filter
     * @return int
     */
    public function count($filter = null)
    {
        if (is_null($filter)) {
            return $this->countObjectsInCollection();
        } else if (is_string($filter)) {
            json_decode($filter);
            $isJson = (json_last_error() === JSON_ERROR_NONE) ? true :false;
            if ($isJson) {
                $filter = new Filter($filter);
                return $this->countObjectsInCollectionByFilter($filter);
            }
        } else if (is_object($filter) && $filter instanceof Filter) {
            return $this->countObjectsInCollectionByFilter($filter);
        }
        return 0;
    }

    private function isValidJson($json)
    {
        return (
            is_string($json)
            && is_array(json_decode($json, true))
            && (json_last_error() === JSON_ERROR_NONE)
        );
    }

    /**
     * After an object has been fully indexed, the object needs to be updated to
     * indicated it is ready to be used within the collection.
     *
     * @param string $id
     * @param int $revision
     */
    private function commitObject($id, $revision)
    {
        $update = '';
        //if version tracking is disabled, delete previous revisions of the object
        if (!$this->options->versionTracking) {
            $update .= Statement::get(
                'DELETE_OBJECT_EXCEPT_REVISION',
                [
                    '@collection' => $this->name,
                    '@id' => $this->connector->quote($id),
                    '@revision' => $this->connector->quote($revision)
                ]
            );
        }
        $update .= Statement::get(
            'UPDATE_OBJECT_TO_COMMITTED',
            [
                '@collection' => $this->name,
                '@id' => $this->connector->quote($id),
                '@revision' => $this->connector->quote($revision)
            ]
        );

        $this->connector->exec($update);
    }

    /**
     * This method is used to dynamically create tokens to help manage
     * temporary tables within SQL queries.
     *
     * @return string A randomly genereated token
     */
    private function generateAlphaToken()
    {
        $timestamp = strtotime($this->generateTimestamp());
        $characters = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
        shuffle($characters);
        $randomString = '';
        foreach (str_split((string) $timestamp) as $num) {
            $randomString .= $characters[$num];
        }
        return $randomString;
    }

    /**
     * This method creates a random revision number stamp.
     *
     * @return int
     */
    private function generateRevisionNumber()
    {
        return rand(1000001, 9999999);
    }

    /**
     * Generates a timestamp with micro seconds.
     *
     * @return string
     */
    private function generateTimestamp()
    {
        $time = microtime(true);
        $micro = sprintf('%06d', ($time - floor($time)) * 1000000);
        $date = new DateTime(date('Y-m-d H:i:s.' . $micro, $time));
        return $date->format("Y-m-d H:i:s.u");
    }

    /**
     * Generates a unique id based on a timestamp so that it is truely unique.
     *
     * @return string
     */
    private function generateUniqueId()
    {
        $rand = uniqid(rand(10, 99));
        $time = microtime(true);
        $micro = sprintf('%06d', ($time - floor($time)) * 1000000);
        $date = new DateTime(date('Y-m-d H:i:s.' . $micro, $time));
        return sha1($date->format('YmdHisu'));
    }

    /**
     * Returns an object in the collection. If the revision isn't provided, the
     * latest revision of the object will be returned.
     *
     * @param string $id
     * @param int $revision
     * @return object|null
     */
    private function getObject($id, $revision = null)
    {
        if ($revision === null) {
            $select = Statement::get(
                'GET_CURRENT_OBJECT',
                [
                    '@collection' => $this->name,
                    '@id' => $this->connector->quote($id)
                ]
            );
            $record = $this->connector->query($select)->fetch();
            if ($record) {
                $object = json_decode($record['obj']);
                if ($this->options->model) {
                    $model = new $this->options->model();
                    return $this->mergeObjects($model, $object);
                }

                return $object;
            }
        }
        return null;
    }

    /**
     * Returns an object's origin timestamp date.
     *
     * @param string $id
     * @return string|null
     */
    private function getObjectOrigin($id)
    {
        $select = Statement::get(
            'GET_OBJECT_ORIGIN_DATE',
            [
                '@collection' => $this->name,
                '@id' => $this->connector->quote($id)
            ]
        );
        $record = $this->connector->query($select)->fetch();
        return ($record) ? $record['updated'] : null;
    }

    /**
     * Returns a collection of objects that matches a filter.
     *
     * @param Filter $filterQuery
     * @return array
     */
    private function getObjectsByFilter(Filter $filterQuery)
    {
        $records = [];
        $props = [];
        $filters = [];
        foreach ($filterQuery->getComparisons() as $comparison) {
            $props[] = $comparison->prop;
            if ($comparison->expression && $comparison->comparison && $comparison->prop) {
                $expression = ($comparison->expression !== 'WHERE') ? $comparison->expression . ' ' : '';
                $prop = is_int($comparison->val) ? 'CAST(' . $comparison->prop . ' AS INT)' : $comparison->prop;
                $compare = $comparison->comparison;
                $value = (!isset($comparison->val) || is_null($comparison->val)) ? 'NULL' : $comparison->val;
                $filters[] = $expression . $prop . ' ' . $compare . ' \'' . $value . '\'';
            }
        }

        $props = array_unique($props);
        $joins = [];
        foreach ($props as $prop)
        {
            $standardFields = [
                '__id',
                '__type',
                '__collection',
                '__origin'
            ];
            if (!in_array($prop, $standardFields)) {
                $asTbl = $this->generateAlphaToken();
                $joins[] =
                    'JOIN(' .
                        'SELECT id, val AS ' . $prop . ' ' .
                        'FROM ' . $this->name . '__index ' .
                        'WHERE prop = \'' . $prop . '\'' .
                    ') AS ' . $asTbl . ' ' .
                    'ON A.id = ' . $asTbl . '.id';
            }
        }

        $select = Statement::get(
            'GET_OBJECTS_BY_FILTER',
            [
                '@collection' => $this->name,
                '@columns' => (count($props) > 0) ? ', ' . implode($props, ', ') : '',
                '@joinColumns' => (count($joins) > 0) ? implode($joins, ' ') . ' ' : '',
                '@type' => $this->connector->quote($filterQuery->getIndexType()),
                '@filters' => ($filters) ? 'AND (' . implode($filters, ' ') . ') ' : '',
                '@order' => $filterQuery->getOrderBy(),
                '@reverse' => ($filterQuery->getReverse()) ? 'DESC' : 'ASC',
                '@limit' => $filterQuery->getLength(),
                '@offset' => $filterQuery->getOffset()
            ]
        );

        $result = $this->connector->query($select);
        if ($result) {
            $records = $result->fetchAll();
        }
        return ($records) ? array_map([$this, 'mapObjectIds'], $records) : [];
    }

    /**
     * Determines if a property is indexable.
     *
     * @param string $property
     * @return boolean
     */
    private function isPropertyIndexable($property)
    {
        $indexBlacklist = ['__id', '__revision', '__updated', '__origin'];
        return !in_array($property, $indexBlacklist);
    }

    /**
     * Determines if a value is indexable.
     *
     * @param mixed $value
     * @return boolean
     */
    public function isValueIndexable($value)
    {
        return (
            is_string($value)
            || is_null($value)
            || is_bool($value)
            || is_integer($value)
        );
    }

    /**
     * Method used with array_map() to return an object
     * for a given ID.
     *
     * @param object $record
     * @return object|null
     */
    private function mapObjectIds($record)
    {
        return $this->getObject($record['__id']);
    }

    /**
     * Updates an object's "value" index.
     *
     * @param object $object
     */
    private function updateObjectIndexes($object)
    {
        // delete all indexed references to this object
        $update = Statement::get(
            'DELETE_OBJECT_INDEX',
            [
                '@collection' => $this->name,
                '@id' => $this->connector->quote($object->__id)
            ]
        );
        // parse each property of the object an attempt to index each value
        foreach (get_object_vars($object) as $property => $value) {
            if (
                $this->isPropertyIndexable($property)
                && $this->isValueIndexable($value)
            ) {
                $insert = Statement::get(
                    'INSERT_OBJECT_INDEX',
                    [
                        '@collection' => $this->name,
                        '@type' => $this->connector->quote('value'),
                        '@prop' => $this->connector->quote($property),
                        '@val' => $this->connector->quote($value),
                        '@id' => $this->connector->quote($object->__id),
                        '@origin' => $this->connector->quote($object->__origin)
                    ]
                );
                $update .= $insert;
            }
        }
        // add the object registry index
        $insert = Statement::get(
            'INSERT_OBJECT_INDEX',
            [
                '@collection' => $this->name,
                '@type' => $this->connector->quote('registry'),
                '@prop' => $this->connector->quote(''),
                '@val' => $this->connector->quote(''),
                '@id' => $this->connector->quote($object->__id),
                '@origin' => $this->connector->quote($object->__origin)
            ]
        );
        $update .= $insert;
        // execute all the sql to update indexes.
        $this->connector->exec($update);
    }

    /**
     * Upserts an object into the collection. Since update and inserts are the same logic,
     * this method handles both.
     *
     * @param object $object
     * @param string $id
     * @return object
     */
    private function upsert($object, $id = null)
    {
        $this->validateObjectType($object);
        $object = $this->writeObjectToDb($object, $id);
        $this->updateObjectIndexes($object);
        $this->commitObject($object->__id, $object->__revision);
        return $object;
    }

    /**
     * Part of the upsert process, this method contains logic to write an object
     * to the database. This method will also add the appropriate meta data to the object
     * and return it.
     *
     * @param object $object
     * @param string $id
     * @return object
     */
    private function writeObjectToDb($object, $id)
    {
        $objectId = (!is_null($id)) ? $id : $this->generateUniqueId();
        $origin = $this->getObjectOrigin($objectId);
        $object = json_decode(json_encode($object));
        $object->__id = $objectId;
        $object->__revision = $this->generateRevisionNumber();
        $object->__updated = $this->generateTimestamp();
        $object->__origin = ($origin) ? $origin : $object->__updated;

        //insert into database
        $insert = Statement::get(
            'INSERT_OBJECT',
            [
                '@collection' => $this->name,
                '@id' => $this->connector->quote($object->__id),
                '@revision' => $this->connector->quote($object->__revision),
                '@committed' => $this->connector->quote(0),
                '@updated' => $this->connector->quote($object->__updated),
                '@origin' => $this->connector->quote($object->__origin),
                '@obj' => $this->connector->quote(json_encode($object))
            ]
        );
        $this->connector->exec($insert);
        if ($this->options->model) {
            $model = new $this->options->model();
            return $this->mergeObjects($model, $object);
        }

        return $object;
    }

    /**
     * This method is used will return the count of objects contained with
     * the collection.
     *
     * @return int
     */
    private function countObjectsInCollection()
    {
        $select = Statement::get(
            'GET_COLLECTION_OBJECT_COUNT',
            [
                '@collection' => $this->name
            ]
        );
        $count = $this->connector->query($select)->fetch();
        if ($count) {
            return (int) $count[0];
        } else {
            return 0;
        }
    }

    /**
     * This method is used to return an object count by the filter that is passed in.
     *
     * @param Filter $filterQuery
     * @return int
     */
    private function countObjectsInCollectionByFilter(Filter $filterQuery)
    {
        $records = [];
        $props = [];
        $filters = [];
        foreach ($filterQuery->getComparisons() as $comparison) {
            $props[] = $comparison->prop;
            if ($comparison->expression && $comparison->comparison && $comparison->prop) {
                $expression = ($comparison->expression !== 'WHERE') ? $comparison->expression . ' ' : '';
                $prop = is_int($comparison->val) ? 'CAST(' . $comparison->prop . ' AS INT)' : $comparison->prop;
                $compare = $comparison->comparison;
                $value = (!isset($comparison->val) || is_null($comparison->val)) ? 'NULL' : $comparison->val;
                $filters[] = $expression . $prop . ' ' . $compare . ' \'' . $value . '\'';
            }
        }

        $props = array_unique($props);
        $joins = [];
        foreach ($props as $prop)
        {
            $standardFields = [
                '__id',
                '__type',
                '__collection',
                '__origin'
            ];
            if (!in_array($prop, $standardFields)) {
                $asTbl = $this->generateAlphaToken();
                $joins[] =
                    'JOIN(' .
                        'SELECT id, val AS ' . $prop . ' ' .
                        'FROM ' . $this->name . '__index ' .
                        'WHERE prop = \'' . $prop . '\'' .
                    ') AS ' . $asTbl . ' ' .
                    'ON A.id = ' . $asTbl . '.id';
            }
        }

        $select = Statement::get(
            'GET_OBJECTS_COUNT_BY_FILTER',
            [
                '@collection' => $this->name,
                '@columns' => (count($props) > 0) ? ', ' . implode($props, ', ') : '',
                '@joinColumns' => (count($joins) > 0) ? implode($joins, ' ') . ' ' : '',
                '@type' => $this->connector->quote($filterQuery->getIndexType()),
                '@filters' => ($filters) ? 'AND (' . implode($filters, ' ') . ') ' : ''
            ]
        );

        $count = $this->connector->query($select)->fetch();
        if ($count) {
            return (int) $count[0];
        } else {
            return 0;
        }
    }
    
    /**
     * Deep merges two objects. $obj2 will get merged onto $obj1
     * and $obj1 will be returned. Keeping the context of the original object.
     *
     * @param object $obj1
     * @param object $obj2
     * @return object The merged objects
     */
    private function mergeObjects($obj1, $obj2) {
        if (is_object($obj2)) {
            $keys = array_keys(get_object_vars($obj2));
            foreach ($keys as $key) {
                if (
                    isset($obj1->{$key})
                    && is_object($obj1->{$key})
                    && is_object($obj2->{$key})
                ) {
                    $obj1->{$key} = $this->mergeObjects($obj1->{$key}, $obj2->{$key});
                } elseif (isset($obj1->{$key})
                && is_array($obj1->{$key})
                && is_array($obj2->{$key})) {
                    $obj1->{$key} = $this->mergeObjects($obj1->{$key}, $obj2->{$key});
                } else {
                    $obj1->{$key} = $obj2->{$key};
                }
            }
        } elseif (is_array($obj2)) {
            if (
                is_array($obj1)
                && is_array($obj2)
            ) {
                $obj1 = array_unique(array_merge_recursive($obj1, $obj2), SORT_REGULAR);
            } else {
                $obj1 = $obj2;
            }
        }

        return $obj1;
    }
    
    /**
     * This method contains the logic needed to validate collection options that
     * were configured.
     *
     * @throws \UA1Labs\Fire\SqlException If any options are not valid
     */
    private function validateOptions()
    {
        if (!is_bool($this->options->versionTracking)) {
            throw new SqlException('When setting options for the collection "' . $this->name . '" the option for "versionTracking" must be boolean.');
        }
        
        if (isset($this->options->model) && (!is_string($this->options->model) || !class_exists($this->options->model))) {
            throw new SqlException('When setting options for the collection "' . $this->name . '" the option for "model" did not resolve to a class.');
        }
    }

    /**
     * Validates that the object is the property type based on the 'model' option that this collection
     * was set for.
     *
     * @param object $object The object to validate
     * @throws \UA1Labs\Fire\SqlException If the object type doesn't match the model type set
     */
    private function validateObjectType($object)
    {
        if (
            $this->options->model 
            && !($object instanceof $this->options->model)
        ) {
            throw new SqlException('The object must be of type "' . $this->options->model . '".');
        }
    }
}
