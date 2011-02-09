<?php
require_once 'Node.php';

//Copyright Christopher Thomas 2003 (cst at andrew dot cmu dot edu)
//You may use and redistribute freely as long as you keep this copyright
//notice. Feel free to modify as you want
class BinaryTree {
    
    public $root;
    
    function BinaryTree() {
        $this->root = null;
    }
    
    function search($key) {
        if ($this->root == null) {
            return false;
        }
        return $this->root->search($key);
    }
    
    function addItem($item, $indexField = 'id') {
        if ($this->root == null) {
            $this->root = new Node();
            $this->root->data[] = $item;
            $this->root->index = $item[$indexField];
            return true;
        }
        return $this->root->addItem($item[$indexField], $item);
    }
}