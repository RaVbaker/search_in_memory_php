<?php

/**
 * Simple example of use the SearchInMemory
 *
 * @author Rafał Piekarski
 */

require_once 'SearchInMemory.php';

$searcher = new SearchInMemory(array(
  'docs' => array(
    array(
      'id' => 1, 
      'title'=>"Twenty Thousand Leagues Under the Sea", 
      'keywords' => array('Twenty', 'Thousand', 'Leagues', 'Under', 'the', 'Sea'),
      'author' => 'Jules Verne',
    ), 
    array(
      'id' => 2, 
      'title'=>"The Forbidden Sea", 
      'keywords' => array('The', 'Forbidden', 'Sea'), 
      'author' => 'Sheila A. Nielson ',
    ), 
    array(
      'id' => 3, 
      'title'=>"The Girl with the Dragon Tattoo", 
      'keywords' => array('The', 'Girl', 'with', 'the', 'Dragon', 'Tattoo'),
      'author' => 'Stieg Larsson',
    ),
   ), 
   'fulltext_index' => 'keywords',
   'indexes' => array('author'),
  )
);

$results = $searcher->find(array(
  'query' => array(
    'phrase' => "sea",
    'author' => 'Jules Verne',
  ),
 )
);                 
echo "Jules Verne book about the sea is: ", $results['results'][0]['title'],"\n";
// var_dump($results);