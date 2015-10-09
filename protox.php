<?php
class protox
{
	protected $fields = array();
	protected $input = array();
	
	static private $defaultValues = array(
		'int' => 0,
		'double' => 0,
		'string' => '',
		'array' => array(),
	);
	
	static private $convertFunc = array(
		'int' => array('protox', 'toInt'),
		'double' => array('protox', 'toDouble'),
		'string' => array('protox', 'toString'),
		'array' => array('protox', 'toArray'),
		'object' => array('protox', 'toObject'),
	);
	
	static private $path = './';
	
	public function __construct($input = array())
	{
		$this->input = $input;
	}
	
	static public function init($params = array())
	{
		if(isset($params['path'])){
			self::$path = $params['path'];
		}
	}
	
	static public function make($name, $data)
	{
		//��Ա��������������new,,ֻ���������ʼ����
		if(!isset(self::$defaultValues['object'])){
			self::$defaultValues['object'] = new stdClass();
		}
		
		require_once(self::$path . "/$name.php");
		$clsname = "{$name}_protocol";
		if(!class_exists($clsname)){
			throw new Exception("Protocol not defined, name=$name");
		}
		$cls = new $clsname($data);
		return $cls->getData();
	}
	
	private function getData()
	{
		$alone = false;
		if(is_string($this->fields)){
			if($this->fields == '*'){
				//�������Բ�����ֱ��ʹ��
				return $this->input;
			}else{
				//�������ݣ�������key
				$alone = true;
				$this->fields = array($this->fields);
				$this->input = array($this->input);
			}
		}

		$data = array();
		foreach($this->fields as $k => $v){
			$opt = $this->parseOption($v);
			if(isset($this->input[$k])){
				$func = self::$convertFunc[$opt['type']];
				if($func){
					//����ת��
					$output = $func($this->input[$k], $opt);
				}else{
					//��������
					$output = self::make($opt['type'], $this->input[$k]);
				}
			}else{
				if(!empty($opt['optional'])){
					//��ѡ���
					continue;
				}else if(isset(self::$defaultValues[$opt['type']])){
					//Ĭ��ֵ����
					$output = self::$defaultValues[$opt['type']];
				}else{
					//���ÿ�ֵ����
					$output = self::make($opt['type'], null);
				}
			}
			
			if($alone){
				$data = $output;
			}else{
				$data[$k] = $output;
			}
		}
		
		return $data;
	}
	
	private function parseOption($v)
	{
		$args = explode('|', $v);
		$types = explode('.', $args[0]);
		$info = array(
			'type' => $types[0],
			'subtype' => isset($types[1]) ? $types[1] : null
		);
		
		if(isset($args[1])){
			foreach(explode(',', $args[1]) as $name){
				switch($name){
					case 'optional':
						$info['optional'] = true;
						break;
				}
			}
		}
		
		return $info;
	}
	
	static private function toInt($val, $opt)
	{
		return (int)$val;
	}
	
	static private function toString($val, $opt)
	{
		return (string)$val;
	}
	
	static private function toDouble($val, $opt)
	{
		return (double)$val;
	}
	
	static private function toArray($val, $opt)
	{
		if(empty($val) || !is_array($val)){
			return array();
		}
		
		$subtype = isset($opt['subtype']) ? $opt['subtype'] : null;
		$list = array();
		foreach($val as $v){
			if($subtype){
				if(isset(self::$convertFunc[$subtype])){
					$func = self::$convertFunc[$subtype];
					$list[] = $func($v, null);
				}else{
					$list[] = self::make($subtype, $v);
				}
			}else{
				$list[] = $v;
			}
		}
		
		return $list;
	}
	
	static private function toObject($val, $opt)
	{
		if(empty($val) || (!is_object($val) && !is_array($val))){
			return new stdClass();
		}
		
		$subtype = isset($opt['subtype']) ? $opt['subtype'] : null;
		if($subtype){
			foreach($val as &$v){
				if(isset(self::$convertFunc[$subtype])){
					$func = self::$convertFunc[$subtype];
					$v = $func($v, null);
				}else{
					$v = self::make($subtype, $v);
				}
			}
		}
		
		return (object)$val;
	}
}