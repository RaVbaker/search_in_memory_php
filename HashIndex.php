<?php
  
/**
 * Class for supporting hashindex
 *
 * @package search_in_memory
 * @author Rafał Piekarski
 */
class HashIndex implements Countable {    
  
    /**
     * Index PHP hash
     *
     * @var array
     */
    protected $_list = array();
    
    /**
     * Index key name
     *
     * @var string
     */
    protected $_keyName = null;
              
    /**
     * Constructor
     *
     * @param array $data 
     * @author Rafał Piekarski
     */
    function __construct(array $data = array()) {
        if (!empty($data)) {
            $this->index($data);
        }
    }
                  
    /**
     * Basic indexing function
     *
     * @param array $data 
     * @return bool
     * @author Rafał Piekarski
     */
    public function index(array $data = array()) {
        $this->_list = array();
        foreach ($data as $pkey => $key) {
            $this->_list[$key][] = $pkey;
        }
        return true;
    }
    
    /**
     * Indexing data with defined key which would be specified from single row
     *
     * @param string $key Indexed field nme
     * @param array $data 
     * @return bool
     * @author Rafał Piekarski
     */
    public function indexWithKey($key, array $data = array()) {
        $this->_list = array();
        $this->_keyName = $key;
        foreach ($data as $pkey => $record) {
            if (is_array($record[$key])) {
                foreach ($record[$key] as $word) {
                    $this->_list[$word][] = $pkey;
                }
            } else {
                $this->_list[$record[$key]][] = $pkey;
            }
        }
        return true;
    }
    
    /**
     * Indexing with defined key only a other value from single data row
     *
     * @param string $key Index field
     * @param string $pkey Data indexed field
     * @param array $data 
     * @return bool
     * @author Rafał Piekarski
     */
    public function indexWithKeyAndPKey($key, $pkey, array $data = array()) {
        $this->_list = array();
        $this->_keyName = $key;
        foreach ($data as $record) {
            if (is_array($record[$key])) {
                foreach ($record[$key] as $word) {
                    $this->_list[$word][] = $record[$pkey];
                }
            } else {
                $this->_list[$record[$key]][] = $record[$pkey];
            }
            
        }
        return true;
    }
    
    /**
     * Count of all elements in index
     *
     * @return int
     * @author Rafał Piekarski
     */
    public function count() {
        return count($this->_list);
    }
                  
    /**
     * Indexing whole data base on defined main key field
     *
     * @param string $pkey 
     * @param array $data 
     * @return bool
     * @author Rafał Piekarski
     */
    public function indexWithPKey($pkey, array $data = array()) {
        $this->_list = array();
        $this->_keyName = $pkey;
        foreach ($data as $record) {
            if (is_array($record[$pkey])) {
                foreach ($record[$pkey] as $word) {
                    $this->_list[$word][] = $record;
                }
            } else {
                $this->_list[$record[$pkey]] = $record; 
            }
        }
        return true;
    }
                                    
    /**
     * Checks that data exists in index on specified key value
     *
     * @param string $keyValue 
     * @return bool
     * @author Rafał Piekarski
     */
    public function exists($keyValue) {
        return $this->search($keyValue)?true:false;
    }
                  
    /**
     * Returns founded value from index. 
     *
     * @param string $keyValue 
     * @return mixed
     * @author Rafał Piekarski
     */
    public function search($keyValue) {
        $results = $this->_list[$keyValue];
        return $results?$results:false;
    }
                    
    /**
     * Returns all keys from index
     *
     * @return array
     * @author Rafał Piekarski
     */
    public function keys() {
        return array_keys($this->_list);
    }
}
