<?php

require_once 'HashIndex.php';

/**
 * Class that support searching through indexed data in PHP memory. 
 *
 * @package search_in_memory
 * @author Rafał Piekarski
 */
class SearchInMemory {
    
    /**
     * Data in raw version
     *
     * @var array
     */
    protected $_docs;
    
    /**
     * Primary index for searches
     *
     * @var HashIndex
     */
    protected $_primaryIndex = null;
    
    /**
     * All index data
     *
     * @var array
     */
    protected $_indexes = array();
    
    
    /**
     * Constructor
     *
     * @param array $options 
     * @author Rafał Piekarski
     */
    public function __construct($options) {
        $this->_scoreAcceptance = $options['score_aceptance']? $options['score_aceptance'] : 0;
        $docs = (isset($options['docs'])) ? $options['docs'] : array();  
        // other values is 'pl' which force to use soundexPL algorithm
        $this->_lang = $options['lang'] ? $options['lang'] : 'en';
        $this->_docs = $docs;
        $this->fulltextIndex = $options['fulltext_index'];

        if (is_null($this->_primaryIndex) && !empty($this->_docs)) {
            $this->_index($options);
        }
    }
                                 
    /**
     * Index all data from 'docs'
     *
     * @param array $options 
     * @return bool
     * @author Rafał Piekarski
     */
    protected function _index($options) {
        $primaryIndexField = (isset($options['primary_key'])) ? $options['primary_key'] : 'id';

        if (!isset($options['indexes'])) {
            $options['indexes'] = array();
        }
        
        if (isset($options['fulltext_index'])) {
            $this->fulltextIndex = $options['fulltext_index'];
            
            $this->_docs = array_map(array($this, 'extendToFulltextSearch'), $this->_docs);
            $options['indexes'][] = '_soundex_'.$this->fulltextIndex;
            $options['indexes'][] = '_normalized_'.$this->fulltextIndex;
            $options['indexes'][] = '_metaphone_'.$this->fulltextIndex;
            $options['indexes'][] = '_doublemetaphone_'.$this->fulltextIndex;
        }
        
        $this->_primaryIndex = new HashIndex();
        $this->_primaryIndex->indexWithPKey($primaryIndexField, $this->_docs);
        
        foreach ($options['indexes'] as $indexField) {
            $index = new HashIndex();

            $index->indexWithKeyAndPKey($indexField, $primaryIndexField, $this->_docs);
            $this->_indexes[$indexField] = $index;
        }

        return true;
    }
    
    public function extendToFulltextSearch($record) {
        $fulltextIndex = $this->fulltextIndex;
                                                    
                                  
        $this->_soundexFunctionCallback = 'soundex';
        if ($this->_lang == 'pl') {
          $this->_soundexFunctionCallback = array($this, '_soundexPLencode');
        }
        
        if (is_array($record[$fulltextIndex])) {
            $record['_soundex_'.$fulltextIndex] = array_map($this->_soundexFunctionCallback, $record[$fulltextIndex]);
        } else {
            $record['_soundex_'.$fulltextIndex] = call_user_func($this->_soundexFunctionCallback, $record[$fulltextIndex]);
        }
        
        if (is_array($record[$fulltextIndex])) {
            $record['_normalized_'.$fulltextIndex] = array_map(array($this, '_normalizeKeywords'), $record[$fulltextIndex]);
        } else {
            $record['_normalized_'.$fulltextIndex] = $this->_normalizeKeywords($record[$fulltextIndex]);
        }
        
        
        if (is_array($record[$fulltextIndex])) {
            $record['_metaphone_'.$fulltextIndex] = array_map('metaphone', $record[$fulltextIndex]);
        } else {
            $record['_metaphone_'.$fulltextIndex] = metaphone($record[$fulltextIndex]);
        }
        
        if (is_array($record[$fulltextIndex])) {
            $record['_doublemetaphone_'.$fulltextIndex] = array_map(array($this, '_doubleMetaphone'), $record[$fulltextIndex]);
        } else {
            $record['_doublemetaphone_'.$fulltextIndex] = $this->_doubleMetaphone($record[$fulltextIndex]);
        }
        
        return $record;
    }
    
    /**
     * Simple algorithm of searching in prepared earlier indexes and making all the ranking, filtering, sorting etc. stuff.
     *
     * @param array $query  Query to use
     * @return array                                                                                                       
     * @author Rafal Piekarski
     */
    public function find(array $attributes) {
        // fields in query:
        $phrase = $attributes['query']['phrase'];
        // example data attributes to examinate, for example: $filters = array('producers' => array('Sony','Philips'),'shop_ids' => array(1245, 3323)): 
        $filters = $attributes['query'];
        unset($filters['phrase']);
        // ~
        // rest of search params:
        $sort = $attributes['sort']['direction'];
        $sortBy = $attributes['sort']['field'];
        $limit = $attributes['paging']['limit'];
        $offset = $attributes['paging']['offset'];
        $fields = $attributes['fields'];
        $facetsConfiguration = $attributes['facets'];

        // all hits index:
        $hits = array();
        
        // if there is a phrase, examinate fulltext index:
        if (!empty($phrase)) {
            $hits[$this->fulltextIndex] = array();
            $fulltextHits = array();
            // filter through each keyword...
            $keywords = explode(' ', $phrase);
            foreach ($keywords as $keyword) {
                
                foreach(array(
                        '_normalized_'.$this->fulltextIndex => array('keyword' => $this->_normalizeKeywords($keyword), 'weight' => 1), 
                        '_soundex_'.$this->fulltextIndex => array('keyword' => call_user_func($this->_soundexFunctionCallback, $keyword), 'weight' => 0.8), 
                        '_metaphone_'.$this->fulltextIndex => array('keyword' => $this->_metaphone($keyword), 'weight' => 0.5), 
                        '_doublemetaphone_'.$this->fulltextIndex => array('keyword' => $this->_doubleMetaphone($keyword), 'weight' => 0.6), 
                        $this->fulltextIndex => array('keyword' => $keyword, 'weight' => 1),
                ) as $indexName => $keywordDef) {
                    $keywordValue = $keywordDef['keyword'];
                    $weight = $keywordDef['weight'];
                        // not found, continue:
                    if (!$this->_indexes[$indexName] || !$this->_indexes[$indexName]->exists($keywordValue)) {
                        continue;
                    }
                    
                    foreach ($this->_indexes[$indexName]->search($keywordValue) as $index) {
                        if (!isset($fulltextHits[$index])) {
                            $fulltextHits[$index] = 0;
                        }
                                               
                        // founded! increment the hits index with specified weight for index.
                        $fulltextHits[$index] += $weight;
                    }
                }
            }

            // early sorting for word score sorting
            if ($sort == 'ASC') {
                asort($fulltextHits);
            } else {
                arsort($fulltextHits);
            }
            // counting the best hit, useful for testing score_aceptance 
            if (!empty($fulltextHits)) {
                $bestHit = max(array_values($fulltextHits));
            }
            $hits[$this->fulltextIndex] = array_keys($fulltextHits);
        } else {
            // no words... so, score_aceptance for all!
            $bestHit = 0;
        }
        
                                  
        // filtering all data:
        foreach ($filters as $filterName => $filterValues) {
          // wyniki dla $producers
          if (!empty($filterValues) && $this->_indexes[$filterName]) {
              // initialize filter hits list:
              $hits[$filterName] = array();
              // normalize query to always array form
              if (!is_array($filterValues)) { 
                $filterValues = array($filterValues);
              }
              foreach ($filterValues as $filterValue) {                             
                  //search for phrase:
                  $hittedIndex = $this->_indexes[$filterName]->search($filterValue);
                  if ($hittedIndex) {                                   
                      // merge with previous results
                      $hits[$filterName] = array_merge($hits[$filterName], $hittedIndex);
                  }
              }
          }
        }
        
        $resultIds = array();
        // merging results with AND between filters:
        if (count($hits) > 1) {
            $resultIds = call_user_func_array('array_intersect', $hits);
        } else {
            $resultIds = current($hits);
        }
        
        // tables for results
        $results = array();
        $facets = array();
        
        // assigning full records data to results
        foreach ($resultIds as $resultId) {
            // from primaryIndex based on primary key
            $result = $this->_primaryIndex->search($resultId);
            // set a score based on fulltextsearch
            $result['score'] = (float)$fulltextHits[$resultId];
            
            // is hit acceptable?
            if ($bestHit * $this->_scoreAcceptance <= $result['score'] ) {
                
                // creating facets (I know it could be optimized)
                foreach($facetsConfiguration as $facetField => $facetName) {
                    $values = $result[$facetField];
                    if (!is_array($values)) {
                        $values = array($values);
                    }
                    foreach ($values as $value) {
                        if (!isset($facets[$facetName][$value])) {
                            $facets[$facetName][$value] = 0;
                        }
                        $facets[$facetName][$value]++;
                    }
                }
                
                // filtering fields from results
                if (!empty($fields)) {
                    $filteredResult = array();
                    foreach ($fields as $field) {
                        $filteredResult[$field] = $result[$field];
                    }
                    $result = $filteredResult;
                }

                // adding record to results
                $results[] = $result;
            }
        }
        
        
        // sorting once again through defined field
        if (!empty($sortBy) && $sortBy !== 'score') {
            // ascending or descending?
            $sortDirSign = $sort === 'ASC' ? '>' : '<';
            // sorting function:
            $sortFunction = create_function('$a, $b', " \$av = \$a['$sortBy']; \$bv = \$b['$sortBy']; return \$av===\$bv? 0 : (\$av $sortDirSign \$bv? 1 : -1); ");
            // sort array with function:
            uasort($results, $sortFunction);
        }
        
       
        
        // count of all results available to get
        $allResultsCount = count($results);
        
        // using LIMIT i OFFSET on results
        if (empty($limit)) {
            $limit = count($results);
        }
        $results = array_slice($results, $offset, $limit, true);

        
        // sorting facets through popularity (highest on top)
        foreach ($facets as $facetName => $facet) {
            arsort($facet);
            $facets[$facetName] = $facet;
        }
        
        
        return array(
          'request' => $attributes,
          'results' => $results,
          'facets' => $facets,
          'total' => $allResultsCount,
          'count' => count($results)
        );
    }
    
    
         
    /**
     * Make a use of founded in web implementation of double metaphone algorithm
     *
     * @param string $word 
     * @return string
     * @author Rafał Piekarski
     */
    protected function _doubleMetaphone($word) {
        $dm = new DoubleMetaPhone($word);
        return $dm->type('secondary');
    }
                     
    /**
     * Also founded on web implementation of metaphone algorithm, currently not used
     *
     * @param string $word 
     * @return bool
     * @author Rafał Piekarski
     */
    protected function _metaphone($word) {
        $dm = new DoubleMetaPhone($word);
        return $dm->type('secondary');
    }
    
    /**
     * Encode word using soundexPL algorithm
     *
     * @param string $word 
     * @return string
     * @author Rafal Piekarski
     */
    protected function _soundexPLencode($word) {
        
        // change doublechars
        $soundexedWord = strtr(mb_strtoupper($word), array(
            'CH' => 'H',   'RZ' => 'Ż',  'CI' => 'Ć',  'NI' => 'Ń',  'SI' => 'Ś',  'ON' => 'Ą',  'KS' => 'X',
            'TRZ' => 'C',  'DZ' => 'Ż',  'CI' => 'Ć',  'MI' => 'Ń',  'ZI' => 'Ź',  'OM' => 'Ą',  'KŚ' => 'X',
            'TCZ' => 'C',  'DŻ' => 'Ż',
            'CZ' => 'C',   'DŹ' => 'Ż',
            'SZ' => 'C',
        ));
        
        $firstCharAfterPairsConversion = mb_substr($soundexedWord, 0, 1);
        
        // change to letters
        $soundexedWord = strtr($soundexedWord, array(
            'A' => '0',  'B' => '1',  'C' => '2',  'D' => '3',  'L' => '4',  'M' => '5',  'R' => '6',  'G' => '7',
            'E' => '0',  'F' => '1',  'S' => '2',  'T' => '3',  'Ł' => '4',  'N' => '5',               'H' => '7',
            'I' => '0',  'P' => '1',  'Z' => '2',                            'Ń' => '5',               'K' => '7',
            'J' => '0',  'V' => '1',  'Ć' => '2',                                                      'Q' => '7',
            'O' => '0',  'W' => '1',  'Ś' => '2',                                                      'X' => '7',
            'U' => '0',               'Ż' => '2',
            'Y' => '0',               'Ź' => '2',
            'Ą' => '0',
            'Ę' => '0',
            'Ó' => '0',
        ));
        
        $soundexedWord = $firstCharAfterPairsConversion.str_replace('0', '', $soundexedWord);
        
        // make a short as 5chars + add '00' at the end
        
        $soundexedWord = mb_substr($soundexedWord.'0000', 0, 5);
        
        //and now we have a word!
        return $soundexedWord;
    }
    
    /**
     * Normalize keywords, specially from polish chars
     *
     * @param string $keywords
     * @return string
     */
    private function _normalizeKeywords($keywords) {
        $search  = array('Ę','Ó','Ą','Ś','Ł','Ż','Ź','Ć','Ń','ę','ó','ą','ś','ł','ż','ź','ć','ń');
        $replace = array('E','O','A','S','L','Z','X','C','N','e','o','a','s','l','z','x','c','n');
        $keywords = str_replace($search, $replace, $keywords);
        $keywords = preg_replace('/([^0-9a-z ]+)/m', '', strtolower($keywords));
        return $keywords;
    }
}






