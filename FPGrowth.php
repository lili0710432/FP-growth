<?php
/*
php 5.3已经支持多线程，见pecl的pthread扩展，真正的多线程支持。
闭包: 内部函数使用了外部函数中定义的变量.  
php5.3中，用use来使用闭包外部定义的变量的,use用来捕捉变量到匿名函数内
以传值方式传递的基础类型参数, 闭包use的值$items在闭包创建是就确定了.

@test sample
$inputArr=array(
	array(2,3,1,4,5),
	array(2,1,4),
	array(2,3,5),
	array(3,4,5),
	array(2,3,1,4),
	array(2,3,1),
);
$obj = new FPGrowth();
$re = $obj->findFrequentItemSets($inputArr,3,1);
var_dump($re);
*/


ini_set('memory_limit','10G');
class  FPGrowth{
    private function conditionalTreeFromPaths($paths,$conditionItem){
    	if( empty($conditionItem) ||  empty($paths) ){	return  false;	}
        $tree = new FPTree();
        foreach ($paths as $path) {
        	if(empty($path[0]) || empty($path[1])){	continue;	}
            $point = $tree->getRoot();
            foreach ($path[0] as $node) {
                $nextPoint = $point->search($node->getItem());
                if (empty($nextPoint)) {
                    $nextPoint = new FPNode($tree, $node->getItem(), $path[1]);
                    $point->add($nextPoint);
                    $tree->updateRoute($nextPoint);
                }
                $point = $nextPoint;
            }
        }
        //$tree->inspect();
	    return $tree;
    }
    private function findWithSuffix($tree, & $suffix, $minimum, $includeSupport, & $re){
    	if(empty($tree)){	return;		}
        foreach ($tree->getItems() as $item => $nodes) {        
            $support = 0;
            foreach ($nodes as $v) {
            	$support += $v->getCount();
            }
            if($support >= $minimum  && !isset($suffix[$item])  ) {
            	//原始逆序item后面的元素不会出现在suffix中,所以此处不用判断suffix是否在re中
                array_unshift($suffix,$item);
                //保留全部的频繁项集合
                //$re[]  	  = $includeSupport ? array($suffix, $support) : $suffix;        
                $condTree = $this->conditionalTreeFromPaths($tree->prefixPaths($item),$item);
                if( !empty($condTree) ){
                	$this->findWithSuffix($condTree, $suffix, $minimum, $includeSupport,$re);
            	}else{//只保留最大的集合 && 集合元素个数大于1
            		if(count($suffix)>1){	
                		$re[] = $includeSupport ? array($suffix, $support) : $suffix;  
               		}
            	}
            	array_shift($suffix);
            }
        }
    }
    public function findFrequentItemSets($transactions, $minimum, $includeSupport = false){
        $items = array();				//二维数组  	array  =  array  1,2,3,4,5
        foreach ($transactions as $v) {
            foreach ($v as $v1) {
                $items[$v1] = isset($items[$v1]) ? $items[$v1] + 1 : 1;
            }
        }
        foreach ($items as $k => $v) {
            if ($v < $minimum) {
                unset($items[$k]);
            }
        }
        $cleanTransaction = function ($transaction) use ($items) {
            $ret = array();
            foreach ($transaction as $v) {
                if (isset($items[$v])) {
                    $ret[$v] = $items[$v];
                }
            }
            arsort($ret);
            return array_keys($ret);
        };
        $master = new FPTree();
        foreach ($transactions as $v) {
        	$master->add($cleanTransaction($v));
        }
        //$master->inspect();
        $re =array();
        $suffix =array();
        $this->findWithSuffix($master, $suffix, $minimum, $includeSupport,$re);
        return $re;
    }
}
class  FPTree{
    private $_root;			//树的根节点
    private $_routes = array();	//树的头指针列表
    public function __construct(){
        $this->_root = new FPNode($this, null, null);
    }
    public function getRoot(){
        return $this->_root;
    }
    public function updateRoute($point){//更新头(尾)指针表
        if ($this != $point->getTree()) {
       	 	//如果你的PHP文件定义了命名空间，那么该命名空间下面的class 用法必须加\表示根空间
            throw new \Exception('Can not have a different tree');
        }
        $item = $point->getItem();	//  增加的节点指针 对应的元素
        if (isset($this->_routes[$item])) {
            $route = $this->_routes[$item];
            $route['tail']->setNeighbor($point);
            $this->_routes[$item] = array('head' => $route['head'], 'tail' => $point);
        } else {
            $this->_routes[$item] = array('head' => $point, 'tail' => $point);
        }
    }
    public function add($transaction){		// 增加一条记录，  数组
        $point = $this->_root;
        foreach ($transaction as $v) {
            $nextPoint = $point->search($v);
            if ($nextPoint) {
                $nextPoint->increment();
            } else {
                $nextPoint = new FPNode($this, $v);
                $point->add($nextPoint);	//point 增加孩子指针 nextPoint
                $this->updateRoute($nextPoint);
            }
            $point = $nextPoint;
        }
    }
    public function getItems(){	//获取头指针对应的节点list	 array( item =>  arary(node,node)	)
    	$itemNodeArr =array();
        foreach ($this->_routes as $k => $v) {
            $itemNodeArr[$k] = $this->getNodes($k);
        }
        return  $itemNodeArr;
    }
    public function getNodes($item){ // 获取fp树中元素值 == item的所有节点指针
        $node = null;	
        $nodeArr = array();
        if (isset($this->_routes[$item]['head'])) {
        	$node = $this->_routes[$item]['head'];
        }
        while ($node) {
            $nodeArr[]=$node;
            $node = $node->getNeighbor();
        }
        return $nodeArr;
    }
    public function prefixPaths($item){	//获取元素item对应的前缀路径集合+count值,单条前缀路径最后元素不是item
        $collectPath = function ($node) {
        	if(empty($node))	{	return array();		}
        	$node   = $node->getParent();
        	if(empty($node))	{	return array();		}
            $path = array();
            while ( $node && !$node->isRoot() ) {
                $path[] = $node;
                $node   = $node->getParent();
            }
            return array_reverse($path);	//数组先后顺序反过来
        };
        $ret = array();
        foreach ($this->getNodes($item) as $v) {
        	if($v->getParent()->isRoot()){		continue;	}
            $ret[] = array($collectPath($v),$v->getCount());
        }
        return $ret;
    }
    public function inspect(){
        echo "Tree:" . PHP_EOL;
        $this->_root->inspect(1);
        echo PHP_EOL;	echo "pTable:" . PHP_EOL;
        foreach ($this->getItems() as $k => $v) {
            echo sprintf('%s:', $k);
            foreach ($v as $v1) {
                echo sprintf('->(%s:%s)', $v1->getItem(),$v1->getCount());
            }
            echo  PHP_EOL;
        }
    }
}
class  FPNode{
    private $_tree;		//树指针
    private $_item;		//节点元素
    private $_count;	//count
    private $_parent = null;
    private $_children = array();
    private $_neighbor = null;
    public function __construct($tree, $item, $count = 1){
        $this->_tree = $tree;
        $this->_item = $item;
        $this->_count = $count;
    }
    public function isRoot(){	//是否跟节点
        return empty($this->_item) && empty($this->_count);
    }
    public function isLeaf(){	//是否叶子节点
        return empty($this->_children);
    }
    public function getTree(){  //获取树指针
        return $this->_tree;
    }
    public function getItem(){	//获取节点元素
        return $this->_item;
    }
    public function getCount(){ //节点计数
        return $this->_count;
    }
    public function setCount($n){//计数值+n
        return $this->_count += $n;
    }
    public function increment(){	//原始计数值++
        if ($this->_count === null) {
            throw new \Exception('Root nodes have no associated count.');
        }
        $this->_count++;
    }
    public function getParent(){//获取父节点
        return $this->_parent;
    }
    public function setParent($node){//设置父节点
        if ($node !== null && (!$node instanceof FPNode)) {
            throw new \Exception('A node must have an FPNode as a parent.');
        }
        if ($node && $node->getTree() !== $this->_tree) {
            throw new \Exception('Cannot have a parent from another tree.');
        }
        $this->_parent = $node;
    }
    public function getNeighbor(){ //获取头指针列表下一个原始节点
        return $this->_neighbor;
    }
    public function setNeighbor($node){
        if ($node !== null && (!$node instanceof FPNode)) {
            throw new \Exception('A node must have an FPNode as a neighbor.');
        }
        if ($node && $node->getTree() !== $this->_tree) {
            throw new \Exception('Cannot have a neighbor from another tree.');
        }
        $this->_neighbor = $node;
    }
    public function getChildren(){		//获取孩子指针 数组
        return $this->_children;
    }
    public function isContain($item){	// 是否含有孩子元素
        return isset($this->_children[$item]);
    }
    public function search($item){		//返回孩子元素 对应的指针
        return isset($this->_children[$item]) ? $this->_children[$item] : null;
    }
    public function add($child)	{		//添加孩子指针
        if (!$child instanceof FPNode) {
            throw new \Exception('Can only add other FPNodes as children');
        }
        $item = $child->getItem();
        if (!isset($this->_children[$item])) {
            $this->_children[$item] = $child;
            $child->setParent($this);
        }
    }
    public function inspect($depth = 0){	//以当前节点为跟，递归显示整个子树
        echo str_repeat("  ",$depth) . $this->repr() . PHP_EOL;
        foreach ($this->_children as $v) {
            $v->inspect($depth + 1);
        }
    }
    public function repr(){			//显示当前节点的元素值 + count
        if ($this->isRoot()) {
            return sprintf("(%s root)", get_class($this));
        }
        return sprintf("(%s:%s)", $this->_item, $this->_count);
    }
}
