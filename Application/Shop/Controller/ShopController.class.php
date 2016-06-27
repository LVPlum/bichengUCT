<?php
/**
 * Created by PhpStorm.
 * User: caipeichao
 * Date: 14-3-11
 * Time: PM5:41
 */

namespace Admin\Controller;

use Admin\Builder\AdminConfigBuilder;
use Admin\Builder\AdminListBuilder;
use Admin\Builder\AdminSortBuilder;
use Admin\Builder\AdminTreeListBuilder;
//use Think\Db\Driver\Pdo;


class ShopController extends AdminController
{
    protected $product_cats_model;
    protected $product_model;
    protected $order_model;
    protected $delivery_model;
    protected $message_model;
    protected $coupon_model;
    protected $user_coupon_model;
    protected $address_model;
    protected $product_comment_model;

	protected $order_logic;
	protected $coupon_logic;

    function _initialize()
    {
        $this->product_cats_model = D('Shop/ShopProductCats');
	    $this->product_model = D('Shop/ShopProduct');
	    $this->order_model = D('Shop/ShopOrder');
	    $this->delivery_model = D('Shop/ShopDelivery');
	    $this->message_model = D('Shop/ShopMessage');
	    $this->coupon_model = D('Shop/ShopCoupon');
	    $this->user_coupon_model = D('Shop/ShopUserCoupon');
	    $this->order_logic = D('Shop/ShopOrder','Logic');
	    $this->coupon_logic = D('Shop/ShopCoupon','Logic');
	    $this->address_model = D('Shop/ShopUserAddress');
	    $this->product_comment_model = D('Shop/ShopProductComment');
        parent::_initialize();
    }


	public function index()
	{
		if(!modC('MP_ID', '', 'Shop'))
		{
			//未配置公众号
			redirect(U('shop/shop'));
		}
		else
		{
			redirect(U('shop/product'));
		}
	}

	public function shop()
	{

		$builder             = new AdminConfigBuilder();
		$data = $builder->handleConfig();
		$member_public = M('member_public')->getField('id,public_name');
		array_unshift($member_public,'选择公众号');
		$builder->title('商城基本设置')
			->keyText('TITLE', '商城名称','')
			->keySingleImage('LOGO','店铺logo')
			->keyText('NOTICE','公告')
			->keyBool('STATUS', '商城状态','默认正常')
			->keySelect('MP_ID','收款公众号','',$member_public)
			->buttonSubmit('', '保存')
			->data($data)
			->display();

	}

	/*
	 * 幻灯片
	 */
	public function slides($action='')
	{
		$shop_slides_model = M('shop_slides');
		switch ($action)
		{
			case 'add':
				if(IS_POST)
				{

					$slides = $shop_slides_model->create();
					$slide['sort'] = (empty($slide['sort'])?0:$slide['sort']);
					if(utf8_strlen($slides['title'])>255)
					{
						$this->error('说明不要长于255个字符');
					}

					if(empty($slides['image']))
					{
						$this->error('请设置一张图片');
					}
					if(!empty($slides['id']))
					{
						unset($slides['create_time']);
						$ret = $shop_slides_model->where('id = '.$slides['id'])->save();
					}
					else
					{
						$ret = $shop_slides_model->add();
					}
					if($ret)
					{
						$this->success('添加成功');
					}
					else
					{
						$this->error('添加失败');
					}
				}
				else
				{
					$id = I('id');
					$slides = $shop_slides_model->where('id ='.$id)->find();
					$builder             = new AdminConfigBuilder();
					$builder->title('新增/编辑商城幻灯片')
						->keyId()
						->keytext('title','图片说明')
						->keySingleImage('image','幻灯片图片')
						->keytext('link','链接地址')
						->keytext('sort','排序,从大到小')
						->keyRadio('status','状态','正常/ 隐藏',array(0=>'正常',1=>'隐藏'))
						->keyCreateTime('create_time','创建时间')
						->data($slides)
						->buttonSubmit(U('shop/slides',array('action'=>'add')))
						->buttonBack()
						->display();
				}
				break;
			case 'delete':
				$ids = I('ids');
				is_array($ids) || $ids = array($ids);
				$ret = $shop_slides_model->where('id in ('.implode(',',$ids).')')->delete();
				if($ret)
				{
					$this->success('删除成功');
				}
				else
				{
					$this->error('删除失败');
				}
				break;
			default:
				$page = I('apge');
				$r = I('r');
				$slides = $shop_slides_model->order('sort desc,create_time desc')->page($page,$r)->select();
//				var_dump(__file__.' line:'.__line__,$slides);exit;
				$totalCount = $shop_slides_model->count();
				$builder = new AdminListBuilder();
				$builder
					->title('商城幻灯片')
					->buttonNew(U('shop/slides',array('action'=>'add')),'新增')
					->ajaxButton(U('shop/slides',array('action'=>'delete')),'','删除')
					->keyId()
					->keyImage('image','图片')
					->keytext('title','说明')
					->keytext('link','链接')
					->keyText('sort','排序')
					->keyMap('status','状态',array('正常','隐藏'))
					->keyTime('create_time','创建时间')
					->keyDoAction('admin/shop/slides/action/add/id/###','编辑')
					->data($slides)
					->pagination($totalCount, $r)
					->display();
				break;
		}
	}
	/*
	 * 商品分类
	 */
	public function product_cats($action='',$page=1,$r=10)
	{

		switch($action)
		{
			case 'add':
				if(IS_POST)
				{
//					var_dump(__file__.' line:'.__line__,$_REQUEST);exit;
					$product_cats = $this->product_cats_model->create();
					if (!$product_cats){

						$this->error($this->product_cats_model->getError());
					}
					if(!empty($product_cats['parent_id'] )
						&& (
							($product_cats['parent_id'] ==$product_cats['id']) ||
							(($sun_id = $this->product_cats_model->get_all_cat_id_by_pid($product_cats['id']))
							&& (in_array($product_cats['parent_id'],$sun_id))))
					)
					{
						$this->error('不要选择自己分类或自己的子分类');
					}
					$ret = $this->product_cats_model->add_or_edit_product_cats($product_cats);
					if ($ret)
					{

						$this->success('操作成功。', U('shop/product_cats',array('parent_id'=>I('parent_id',0))));
					}
					else
					{
						$this->error('操作失败。');
					}
				}
				else
				{
					$builder       = new AdminConfigBuilder();
					$id = I('id');
					if(!empty($id))
					{
						$product_cats = $this->product_cats_model->get_product_cat_by_id($id);
					}
					else
					{
						$product_cats = array();
					}

					$select = $this->product_cats_model->get_produnct_cat_config_select();
//					var_dump(__file__.' line:'.__line__,$select);exit;
					$builder->title('新增/修改商品分类')

						->keyId()
						->keyText('title', '分类名称')
						->keyText('title_en', '分类名称英文')
						->keySingleImage('image','图片')
						->keySelect('parent_id','上级分类','',$select)
						->keyText('sort', '排序')
						->keyRadio('stauts','状态','',array('0'=>'正常','1'=>'隐藏'))
						->keyCreateTime()
						->data($product_cats)
						->buttonSubmit(U('shop/product_cats',array('action'=>'add')))
						->buttonBack()
						->display();
				}
				break;
			case 'delete':
				$ids = I('ids');
				$ret = $this->product_cats_model->delete_product_cats($ids);
				if ($ret)
				{

					$this->success('操作成功。', U('shop/product_cats'));
				}
				else
				{
					$this->error('操作失败。');
				}
				break;
			default:

				$option['parent_id'] = I('parent_id',0,'intval');
				if(!empty($option['parent_id']))
				{
					$parent_cat  = $this->product_cats_model->get_product_cat_by_id($option['parent_id']);
				}
				if(I('all')) $option = array();
				$option['page'] = $page;
				$option['r']  =  $r;
				$cats = $this->product_cats_model->get_product_cats($option);
				$totalCount = $cats['count'];
//				var_dump(__file__.' line:'.__line__,$parent_cat);exit;
				$select = $this->product_cats_model->get_produnct_cat_list_select();
				$builder = new AdminListBuilder();
				$builder
					->title((empty($parent_cat)?'顶级的':$parent_cat['title'].' 的子').'商品分类')
					->setSelectPostUrl(U('shop/product_cats'))
					->select('分类查看', 'parent_id', 'select', '', '', '', $select)
//					->buttonNew(U('shop/product_cats',array('all'=>1)),'全部分类')
					->buttonNew(U('shop/product_cats',array('parent_id'=>(empty($parent_cat['parent_id'])?0:$parent_cat['parent_id']))),'上级分类')
					->buttonnew(U('shop/product_cats',array('action'=>'add','parent_id'=>$option['parent_id'])),'新增分类')
					->ajaxButton(U('shop/product_cats',array('action'=>'delete')),'','删除')
//					->keyText('id','id')
					->keyText('title','标题')
					->keyText('title_en','英文标题')
					->keyImage('image','图片')
					->keyText('sort','排序')
					->keyTime('create_time','创建时间')
					->keyStatus('status','状态')
					->keyDoAction('admin/shop/product_cats/action/add/id/###','编辑')
					->keyDoAction('admin/shop/product_cats/parent_id/###','查看下属分类')
					->data($cats['list'])
					->pagination($totalCount, $r)
					->display();
		}
	}

	/*
	 * 商品相关
	 */
	public function product($action = '')
	{
		switch($action)
		{
			case 'add':
				if(IS_POST)
				{

					$product = $this->product_model->create();
					if (!$product){

						$this->error($this->product_model->getError());
					}
					$ret = $this->product_model->add_or_edit_product($product);
					if ($ret)
					{
						$this->success('操作成功。', U('shop/product'));
					}
					else
					{
						$this->error('操作失败。');
					}
				}
				else
				{
					$builder       = new AdminConfigBuilder();
					$id = I('id');
					if(!empty($id))
					{
						$product = $this->product_model->get_product_by_id($id);
					}
					else
					{
						$product = array();
					}

					$select = $this->product_cats_model->get_produnct_cat_config_select('选择分类');
					if(count($select)==1)
					{
						$this->error('先添加一个商品分类吧',U('shop/product_cats',array('action'=>'add')),2);
					}
					$delivery_select = $this->delivery_model->getfield('id,title');
					empty($delivery_select) && $delivery_select=array();
					array_unshift($delivery_select,'不需要运费');
					$info_array = array(
//								'不货到付款','不包邮','不开发票','不保修','不退换货','不是新品',
					                    '6'=>'热销','7'=>'推荐');
					//注释的暂不支持
					$builder->title('新增/修改商品')
						->keyId()
						->keyText('title', '商品名称')
						->keySingleImage('main_img','商品主图')
						->keyMultiImage('images','商品图片,分号分开多张图片')
						->keySelect('cat_id','商品分类','',$select)
						->keyInteger('price', '价格/分','交易价格')
						->keyInteger('ori_price', '原价/分','显示被划掉价格')
						->keyInteger('quantity', '库存')
//						->keyText('product_code', '商家编码,可用于搜索')
						->keyCheckBox('info','其他配置','',$info_array)
//						->keyInteger('back_point', '购买返还积分')
//						->keyInteger('point_price', '积分换购所需分数')
//						->keyInteger('buy_limit', '限购数,0不限购')
//						->keyText('sku_table','商品sku')
//						->keytext('location','货物所在地址')
						->keySelect('delivery_id','运费模板, 可先保存后再修改运费模板,避免丢失已编辑信息','<a target="_blank" href="index.php?s=/admin/shop/delivery">点击添加运费模板</a>',$delivery_select)
						->keyText('sort', '排序')
						->keyRadio('status','状态','',array('0'=>'正常','1'=>'下架'))
						->keyEditor('content', '商品详情','','all')

						->keyCreateTime()
//						->keytime('modify_time','编辑时间')
						->data($product)
						->buttonSubmit(U('shop/product',array('action'=>'add')))
						->buttonBack()
						->display();
				}
				break;
			case 'delete':
				$ids = I('ids');
				$ret = $this->product_model->delete_product($ids);
				if ($ret)
				{

					$this->success('操作成功。', U('shop/product'));
				}
				else
				{
					$this->error('操作失败。');
				}
				break;
			case 'cell_record':
				$option['product_id'] = I('product_id',0);
				$option['user_id'] = I('user_id',0);
//				$option['min_time'] = I('min_time',0);
				$option['page'] = I('page',1);
				$option['r'] = I('r',10);
				$product_sell_model = D('shop/ShopProductSell');
				$product_sell_record = $product_sell_model->get_sell_record($option);
				$totalCount = $product_sell_record['count'];
				$builder = new AdminListBuilder();
				$builder
					->title('商品成交记录')
					->keyText('product_id','商品id')
					->keyText('order_id','订单id')
					->keyText('user_id','用户id')
					->keyText('paid_price','下单价格/（分）')
					->keyText('quantity','下单数目')
					->keyTime('create_time','创建时间')
					->data($product_sell_record['list'])
					->pagination($totalCount, $option['r'])
					->display();
				break;
			case 'delete_sku_table':
				if(IS_POST)
				{
					$product['id'] = I('id','','intval');
					empty($product['id']) && $this->error('缺少商品id');
					$product['sku_table'] = '';
					$ret = $this->product_model->add_or_edit_product($product);
					if ($ret)
					{
						$this->success('操作成功。',U('shop/product',array('action'=>'sku_table','id'=>$product['id'])),1);
					}
					else
					{
						$this->error('操作失败。');
					}
				}
				break;
			case 'sku_table':
				if(IS_POST)
				{
					$product['id'] = I('id','','intval');
					empty($product['id']) && $this->error('缺少商品id');
					$table = I('table');
					$info = I('info');
					$product['sku_table'] = array('table'=>$table,'info'=>$info);
					$product['sku_table'] = json_encode($product['sku_table']);
					$ret = $this->product_model->add_or_edit_product($product);
					if ($ret)
					{
						$this->success('操作成功。');
					}
					else
					{
						$this->error('操作失败。');
					}
				}
				else
				{
					$id = I('id');
					if(empty($id)
					||!($product = $this->product_model->get_product_by_id($id)))
					{
						$this->error('请选择一个商品','',2);
					}
					$this->assign('product', $product);
	                $this->display('Shop@Shop/sku_table');
				}
				
				break;
			case 'exi':
				if(IS_POST)
				{
					//没写完
					var_dump(__file__.' line:'.__line__,$_REQUEST);exit;
					$product = array();
					$ret = $this->product_model->add_or_edit_product($product);
					if($ret)
					{
						$this->success('操作成功',U('shop/product'));
					}
					else
					{
						$this->error('操作失败');

					}
					//					var_dump(__file__.' line:'.__line__, $_REQUEST);exit;

				}
				else
				{
					$porduct_extra_info_model = D('Shop/ShopProductExtraInfo');

					$id = I('id');
					if(empty($id)
						||!($product = $this->product_model->get_product_by_id($id)))
					{
						$this->error('请选择一个商品','',2);
					}
					$exi = $porduct_extra_info_model->get_product_extra_info($id);
					$this->assign('exi', $exi);
					$this->display('Shop@shop/exi');
				}
				break;
			default:

				$option['page'] = I('page',1);
				$option['r'] = I('r',10);
				$option['cat_id'] = I('cat_id');
				$count = I('count');
				if(empty($option['cat_id'])) unset($option['cat_id']);
				$product = $this->product_model->get_product_list($option);
				$totalCount = $product['count'];
				$select = $this->product_cats_model->get_produnct_cat_list_select('全部分类');
				$select2 = $this->product_cats_model->get_produnct_cat_config_select('全部分类');
				$builder = new AdminListBuilder();
				$builder
					->title('商品管理')
					->setSelectPostUrl(U('shop/product'))
					->select('分类查看', 'cat_id', 'select', '', '', '', $select)
					->select('显示模式', 'count', 'select', '', '', '', array(array('id'=>0,'value'=>'正常'),array('id'=>1,'value'=>'统计信息')))
					->buttonnew(U('shop/product',array('action'=>'add')),'新增商品')
					->ajaxButton(U('shop/product',array('action'=>'delete')),'','删除')
					->keyText('id','商品id')
					->keyText('title','商品名');
				if(!$count)
				{
					$builder->keyMap('cat_id','所属分类',$select2)
						->keyText('price','价格/（分）')
						->keyText('quantity','库存')
						->keyImage('main_img','图片')
						//					->keyTime('create_time','创建时间')
						//					->keyTime('modify_time','编辑时间')
						->keyText('sort','排序')
						->keyMap('status','状态',array('0'=>'正常','1'=>'下架'));
				}
				else
				{
					$builder
//						->keyText('like_cnt','点赞数')
//						->keyText('fav_cnt','收藏数')
						->keyText('comment_cnt','评论数')
//						->keyText('click_cnt','点击数')
						->keyText('sell_cnt','总销量')
						->keyText('score_cnt','评分次数')
						->keyText('score_total','总评分');
				}

				$builder->keyDoAction('admin/shop/product/action/add/id/###','基本信息')
					->keyDoAction('admin/shop/product/action/sku_table/id/###','特殊规格')
//					->keyDoAction('admin/shop/product/action/exi/id/###','商品参数')
					->data($product['list'])
					->pagination($totalCount, $option['r'])
					->display();
				break;
		}
	}

	/*
	 *  订单相关
	 */
	public function order($action= '')
	{
		switch($action)
		{
			case 'delete':
				$ids = I('ids');
				$ret = $this->order_logic->delete_order($ids);
				if($ret)
				{
					$this->success('删除成功');
				}
				else
				{
					$this->error('删除失败，'.$this->order_logic->error_str,'',3);
				}
			break;
			case 'order_delivery':
				if(IS_POST)
				{
					$id = I('id');
					empty($id) && $this->error('信息错误',1);
					$courier_no = I('courier_no');
					$courier_name = I('courier_name');
					$courier_phone = I('courier_phone','','intval');
					$delivery_info = array(
						'courier_no'=>$courier_no,
						'courier_name'=>$courier_name,
						'courier_phone'=>$courier_phone,
					);
					$order['delivery_info'] = json_encode($delivery_info);
					$order['id'] = $id;
					$ret = $this->order_model->add_or_edit_order($order);
					if($ret)
					{
						$this->success('操作成功');
					}
					else{
						$this->error('操作失败','',3);
					}
				}
				else{
					$id = I('id');
					$order = $this->order_model->get_order_by_id($id);
					$delivery_info = json_decode($order['delivery_info'],true);
					//				var_dump(__file__.' line:'.__line__,$order);exit;
					$delivery_info['id'] = $order['id'];
					$order['send_time'] = (empty($order['send_time'])?'未发货':date('Y-m-d H:i:s',$order['send_time']));
					$order['recv_time'] = (empty($order['recv_time'])?'未收货':date('Y-m-d H:i:s',$order['recv_time']));

					$delivery_info['send_time'] = $order['send_time'];
					$delivery_info['recv_time'] = $order['recv_time'];
					$builder       = new AdminConfigBuilder();
					$builder
						->title('发货信息')
						->suggest('发货信息')
						->keyReadOnly('id','订单id')
						->keyText('courier_no','快递单号')
						->keyText('courier_name','快递员姓名')
						->keyText('courier_phone','快递员电话')
						->keyText('send_time','发货时间')
						->keyText('recv_time','收货时间')
						->buttonSubmit(U('Shop/order',array('action'=>'order_delivery')),'修改')
						->buttonBack()
						->data($delivery_info)
						->display();
				}
				break;
			case 'order_address':
				$id = I('id');
				$order = $this->order_model->get_order_by_id($id);
				$address = is_array($order['address'])?$order['address']:json_decode($order['address'],true);
				$info  = is_array($order['info'])?$order['info']:json_decode($order['info'],true);

				foreach($info as $ik=>$iv)
				{
					$infos['info_'.$ik] = $iv;
				}

				$builder       = new AdminConfigBuilder();
				$builder
					->title('地址等信息')
					->keyReadOnly('id','订单id')
					->keyJoin('user_id','用户','uid','nickname','member','/admin/user/index')
					->keyText('name','姓名')
					->keyText('phone','手机')
					->keyMultiInput('province|city|town','地址','省|市|区',array(
						array('type'=>'text','style'=>'width:95px;margin-right:5px'),
						array('type'=>'text','style'=>'width:95px;margin-right:5px'),
						array('type'=>'text','style'=>'width:95px;margin-right:5px'),
					))
					->keyText('address','详细地址')
					->keyText('info_remark','备注')
					->keyText('info_fapiao','发票抬头');
				//其他信息 滚出
				foreach($infos as $ik=>$iv)
				{
					if(in_array($ik,array('info_remark','info_fapiao')))
						continue;
					$builder->keyText($ik,$ik);
				}
				$address = is_array($address)?$address:array();
				$builder
					->buttonBack()
					->data(array_merge($address,$infos))
					->display();
				break;
			case 'order_detail':
				$id = I('id');
				$order = $this->order_model->get_order_by_id($id);
				$order['create_time'] =(empty($order['create_time'])?'':date('Y-m-d H:i:s',$order['create_time']));
				$order['paid_time'] =(empty($order['paid_time'])?'未支付':date('Y-m-d H:i:s',$order['paid_time']));
				$order['send_time'] = (empty($order['send_time'])?'未发货':date('Y-m-d H:i:s',$order['send_time']));
				$order['recv_time'] = (empty($order['recv_time'])?'未收货':date('Y-m-d H:i:s',$order['recv_time']));
				$builder       = new AdminConfigBuilder();
//				var_dump(__file__.' line:'.__line__,$order );exit;
				$builder
					->title('订单详情')
					->keyReadOnly('id','订单id')
//					->keytext('')
//					->keyText('use_point','使用积分')
//					->keyText('back_point','返回积分')
					->keytext('create_time','创建时间')
					;
//				$product_input_list = array(
//					'title'=>array('name'=>'商品名','type'=>'keytext'),
//					'quantity'=>array('name'=>'数量','type'=>'keytext'),
//					'paid_price'=>array('name'=>'价格','type'=>'keytext'),
//					'sku_id'=>array('name'=>'其他信息','type'=>'keytext'),
//					'main_img'=>array('name'=>'商品主图','type'=>'keySingleImage'));
				$product_input_list = array(
					'title'=>array('name'=>'商品名','type'=>'text'),
					'quantity'=>array('name'=>'数量','type'=>'text'),
					'paid_price'=>array('name'=>'价格/分','type'=>'text'),
					'sku_id'=>array('name'=>'其他信息','type'=>'text'),
//					'main_img'=>array('name'=>'商品主图','type'=>'SingleImage')
				);
				if(!empty($order['products']))
				{
					foreach($order['products'] as $pk=> $product)
					{
						$MultiInput_name='|';
						foreach($product_input_list as $k=>$kv)
						{
							$name = 'porduct'.$pk.$k;
							if($k == 'sku_id')
							{
								if($product['sku_id'] = explode(';',$product['sku_id']))
								{
									unset($product['sku_id'][0]);
									$order[$name] =(empty($product['sku_id'])?'无':implode(',',$product['sku_id'])) ;
								}
							}
							else
							{
								$order[$name] = $product[$k];
							}
							$order[$name.'title'] = $kv['name'];
//							$builder->$kv['type']($name,$kv['name']);
							$MultiInput_name .= $name.'title'.'|'.$name.'|';
							$MultiInput_array[] =array('type'=>$kv['type'],'style'=>'width:95px;margin-right:5px') ;
							$MultiInput_array[] =array('type'=>$kv['type'],'style'=>'width:295px;margin-right:5px') ;
						}
						$builder->keyMultiInput(trim($MultiInput_name,'|'),'商品['.($pk+1).']信息','',$MultiInput_array);

					}
				}
//				var_dump(__file__.' line:'.__line__,$order);exit;
				$builder
					->keytext('paid_time','支付时间')
					->keyMultiInput('paid_fee|discount_fee|delivery_fee','支付信息(单位：分)','支付金额|优惠金额|运费',array(
						array('type'=>'text','style'=>'width:95px;margin-right:5px'),
						array('type'=>'text','style'=>'width:95px;margin-right:5px'),
						array('type'=>'text','style'=>'width:95px;margin-right:5px'),
					))
					->keyText('send_time','发货时间')
					->keyText('recv_time','收货时间')
					->buttonBack()
					->data($order)
					->display();
			break;
			case 'edit_order_modal':
				if(IS_POST)
				{
					$order_id = I('order_id','','intval');
					$status = I('status','','intval');
					$order = $this->order_model->get_order_by_id($order_id);
					if(empty($order_id) || empty($status) || !($order))
					{
						$this->error('参数错误');
					}
					else
					{
						switch ($status)
						{
							case '1':
								//取消订单
								$ret = $this->order_logic->cancal_order($order);
								if($ret)
								{
									$this->success('操作成功');
								}
								else
								{
									$this->error('操作失败,'.$this->order_logic->error_str);
								}
								break;
							case '2':
								//发货
								$courier_no = I('courier_no');
								$courier_name = I('courier_name');
								$courier_phone = I('courier_phone','','intval');
								$delivery_info = array(
									'courier_no'=>$courier_no,
									'courier_name'=>$courier_name,
									'courier_phone'=>$courier_phone,
								);
								$ret = $this->order_logic->send_good($order,$delivery_info);
								if($ret)
								{
									$this->success('操作成功');
								}
								else
								{
									$this->error('操作失败,'.$this->order_logic->error_str);
								}
								break;
							case '3':
								//确认收货
								$ret = $this->order_logic->recv_goods($order);
								if($ret)
								{
									$this->success('操作成功');
								}
								else
								{
									$this->error('操作失败,'.$this->order_logic->error_str);
								}
								break;
							case '8':
								//拒绝退款
								$refund_reason = I('refund_reason','');
								$this->error('暂不支持该操作,'.$this->order_logic->error_str);
								break;
							case '10':
								//删除订单
								$ret = $this->order_logic->delete_order($order['id']);
								if($ret)
								{
									$this->success('操作成功');
								}
								else
								{
									$this->error('操作失败,'.$this->order_logic->error_str);
								}
								break;
						}

					}
				}
				else{
					$id = I('id');                        //获取点击的ids
					$order = $this->order_model->get_order_by_id($id);
					$this->assign('order', $order);
					$this->display('Shop@Shop/edit_order_modal');
				}


				break;
			default:
				$option['page'] = I('page',1);
				$option['r'] = I('r',10);
				$option['user_id'] = I('user_id');
				$option['status'] = I('status');
				$option['key'] = I('key');
				$option['ids'] = I('id');
				empty($option['ids']) || $option['ids'] = array($option['ids']);
				$option['show_type'] = I('show_type','','intval');
				$order = $this->order_model->get_order_list($option);
//				var_dump(__file__.' line:'.__line__,$order);exit;
				$status_select = $this->order_model->get_order_status_config_select();
				$status_select2 = $this->order_model->get_order_status_list_select();
				$show_type_array = array(array('id'=>0,'value'=>'订单信息'),array('id'=>1,'value'=>'订单状态'));
				$totalCount = $order['count'];
				$builder = new AdminListBuilder();
				$builder
					->title('订单管理')
					->setSearchPostUrl(U('shop/order'))
					->search('', 'id', 'text', '订单id', '', '', '')
					->search('', 'key', 'text', '商品名', '', '', '')
					->select('订单状态：', 'status', 'select', '', '', '', $status_select2)
					->select('显示模式:', 'show_type', 'select', '', '', '', $show_type_array)
					->buttonNew(U('shop/order'), '全部订单')
					->keyText('id','订单id')
					->keyJoin('user_id','用户','uid','nickname','member','/admin/user/index');
//					->ajaxButton(U('shop/order',array('action'=>'delete')),'','删除')
				$option['show_type'] && $builder
					->keyTime('create_time','下单时间')
					->keyTime('paid_time','支付时间')
					->keyTime('send_time','发货时间')
					->keyTime('recv_time','收货时间')
					;

				$option['show_type'] || $builder
					->keyMap('status','订单状态',$status_select)
					->keyText('paid_fee','总价/分')
					->keyText('discount_fee','已优惠的价格')
					->keyText('delivery_fee','邮费')
					->keyText('product_cnt','商品种数')
					->keyText('product_quantity','商品总数');

				$builder->keyDoAction('admin/shop/order/action/order_detail/id/###','订单详情')
					->keyDoAction('admin/shop/order/action/order_address/id/###','地址等信息')
					->keyDoAction('admin/shop/order/action/order_delivery/id/###','发货信息')
					->keyDoActionModalPopup('admin/shop/order/action/edit_order_modal/id/###','订单操作');
				$builder
					->data($order['list'])
					->pagination($totalCount, $option['r'])
					->display();
			break;
		}

	}

	/*
	 * 运费模板
	 */
	public function delivery($action = '')
	{
		switch($action)
		{
			case 'add':
				if(IS_POST)
				{
					$delivery = $this->delivery_model->create();
					if (!$delivery){

						$this->error($this->delivery_model->getError());
					}
//					isset($_REQUEST['express_enable']) && empty($_REQUEST['express_enable']) || $rule['express'] = I('express',0);
//					isset($_REQUEST['mail_enable']) && empty($_REQUEST['mail_enable']) ||$rule['mail'] = I('mail',0);
//					isset($_REQUEST['ems_enable']) && empty($_REQUEST['ems_enable']) ||$rule['ems'] =I('ems',0);
					isset($rule) && $delivery['rule'] =json_encode($rule);
					$ret = $this->delivery_model->add_or_edit_delivery($delivery);
					if ($ret)
					{
						$this->success('操作成功。', U('shop/delivery'),1);
					}
					else
					{
						$this->error('操作失败。');
					}
				}
				else
				{
					$builder       = new AdminConfigBuilder();
					$id = I('id');
					if(!empty($id))
					{
						$delivery = $this->delivery_model->get_delivery_by_id($id);
					}
					else
					{
						$delivery = array();
					}
					$this->assign('delivery',$delivery);
					$this->display('Shop@Shop/adddelivery');exit;
					if(!empty($delivery))
					{
						$delivery['express_enable'] = (isset($delivery['rule']['express'])?1:0);
						$delivery['express'] = (empty($delivery['express_enable'])?'':$delivery['rule']['express']);
						$delivery['mail_enable'] = (isset($delivery['rule']['mail'])?1:0);
						$delivery['mail'] = (empty($delivery['mail_enable'])?'':$delivery['rule']['mail']);
						$delivery['ems_enable'] = (isset($delivery['rule']['ems'])?1:0);
						$delivery['ems'] = (empty($delivery['ems_enable'])?'':$delivery['rule']['ems']);
					}

//					$builder->title('新增/修改运费模板')
//						->keyId()
//						->keyText('title', '模板名称')
////						->keyRadio('valuation','计费方式','',array(' 固定邮费','计件'))
//						->keyMultiInput('express_enable|express','平邮','单位:分',array(array('type'=>'select','opt'=>array('不支持','支持'),'style'=>'width:95px;margin-right:5px'),array('type'=>'text','style'=>'width:95px;margin-right:5px')))
//						->keyMultiInput('mail_enable|mail','普通快递','单位：分',array(array('type'=>'select','opt'=>array('不支持','支持'),'style'=>'width:95px;margin-right:5px'),array('type'=>'text','style'=>'width:95px;margin-right:5px')))
//						->keyMultiInput('ems_enable|ems','EMS','单位：分',array(array('type'=>'select','opt'=>array('不支持','支持'),'style'=>'width:95px;margin-right:5px'),array('type'=>'text','style'=>'width:95px;margin-right:5px')))
//						->keyEditor('brief', '模板说明')
//						->keyCreateTime()
//						->data($delivery)
//						->buttonSubmit(U('shop/delivery',array('action'=>'add')))
//						->buttonBack()
//						->display();
				}
				break;
			case 'delete':
				$ids = I('ids');
				$ret = $this->delivery_model->delete_delivery($ids);
				if ($ret)
				{

					$this->success('操作成功。', U('shop/delivery'));
				}
				else
				{
					$this->error('操作失败。');
				}
				break;
			default:
				$option['page'] = I('page',1);
				$option['r'] = I('r',10);
				$delivery = $this->delivery_model->get_delivery_list($option);
				$totalCount = $delivery['count'];

				$builder = new AdminListBuilder();
				$builder
					->title('运费模板管理')
					->buttonnew(U('shop/delivery',array('action'=>'add')),'新增运费模板')
					->ajaxButton(U('shop/delivery',array('action'=>'delete')),'','删除')
					->keyText('id','id')
					->keyText('title','标题')
					->keyText('brief','模板说明')
//					->keyMap('valuation','计费方式',array())
					->keyTime('create_time','创建时间')
					->keyDoAction('admin/shop/delivery/action/add/id/###','编辑')
					->data($delivery['list'])
					->pagination($totalCount, $option['r'])
					->display();
				break;
		}
	}

	/*
	 * 商城评论反馈
	 */
	public function message($action ='')
	{
		switch($action)
		{
			case 'review_message':
				$ids  = I('ids');
				is_array($ids) || $ids =array($ids);
				$status = I('status','0','intval');
				$ret = $this->message_model->where('id in('.implode(',',$ids).')')->save(array('status'=>$status));
				if ($ret)
				{
					$this->success('操作成功。', U('shop/message'));
				}
				else
				{
					$this->error('操作失败。');
				}
				break;
			case 'message_detail':
				$builder       = new AdminConfigBuilder();
				$id = I('id');
				if(!empty($id))
				{
					$message = $this->message_model->get_shop_message_by_id($id);
				}
				else
				{
					$message= array();
				}
				$builder->title('留言详情和回复')
					->keyId()
					->keyText('user_id','用户id')
					->keyTextArea('brief', '用户留言')
//					->keytext('rebrief','')
					->data($message)
//					->buttonSubmit(U('shop/message',array('action'=>'add')))
//					->buttonBack()
					->display();
				break;
			case 'delete':
				$ids = I('ids');
				$ret = $this->message_model->delete_shop_message($ids);
				if ($ret)
				{
					$this->success('操作成功。', U('shop/message'));
				}
				else
				{
					$this->error('操作失败。');
				}
				break;
			default :
				$option['page'] = I('page',1);
				$option['r'] = I('r',10);
				$message = $this->message_model->get_shop_message_list($option);
				$totalCount = $message['count'];

				$builder = new AdminListBuilder();
				$builder
					->title('商城反馈')
					->ajaxButton(U('shop/message',array('action'=>'review_message','status'=>1)),'','通过审核')
					->ajaxButton(U('shop/message',array('action'=>'review_message','status'=>2)),'','不通过审核')
					->ajaxButton(U('shop/message',array('action'=>'delete')),'','删除')
					->keyText('id','id')
					->keyText('reply_cnt','评论数')
					->keyText('user_id','用户id')
					->keyTruncText('brief','留言内容',25)
					->keyTime('create_time','创建时间')
					->keyMap('status','状态',array('未审核','通过审核','不通过审核'))
					->keyDoAction('admin/shop/message/action/message_detail/id/###','详情')
					->keyDoAction('admin/shop/message/action/review_message/ids/###/status/1','通过审核')
					->keyDoAction('admin/shop/message/action/review_message/ids/###/status/2','不通过审核')
					->data($message['list'])
					->pagination($totalCount, $option['r'])
					->display();
				break;
		}
	}


	/*
	 * 优惠券
	 */
	public function coupon($action = '')
	{
		switch($action)
		{
			case 'add':
				if(IS_POST)
				{
					$coupon = $this->coupon_model->create();
					if(!$coupon)
					{
						$this->error($this->coupon_model->getError());
					}
					empty($_REQUEST['max_cnt_enable']) || $rule['max_cnt'] =I('max_cnt',0,'intval');
					empty($_REQUEST['max_cnt_day_enable']) || $rule['max_cnt_day'] =I('max_cnt_day',0,'intval');
					empty($_REQUEST['min_price_enable']) || $rule['min_price'] =I('min_price',0,'intval');
					if(empty($_REQUEST['discount']))
					{
						$this->error('请设置优惠金额');
					}
					else
					{
						$rule['discount'] =I('discount',0,'intval');
					}
					empty($rule) || $coupon['rule'] = json_encode($rule);

					$ret = $this->coupon_model->add_or_edit_coupon($coupon);
					if ($ret)
					{
						$this->success('操作成功。', U('shop/coupon'));
					}
					else
					{
						$this->error('操作失败。');
					}
				}
				else
				{
					$id = I('id');
					if(!empty($id))
					{
						$coupon = $this->coupon_model->get_coupon_by_id($id);
						if(!empty($coupon['rule']))
						{
							$coupon['rule']['max_cnt_enable'] = (empty($coupon['rule']['max_cnt'])?0:1);
							$coupon['rule']['max_cnt_day_enable'] = (empty($coupon['rule']['max_cnt_day'])?0:1);
							$coupon['rule']['min_price_enable'] = (empty($coupon['rule']['min_price'])?0:1);
							$coupon = array_merge($coupon,$coupon['rule']);
						}
					}
					else
					{
						$coupon =array();
					}
					$builder       = new AdminConfigBuilder();
					$builder->title('优惠券详情')
						->keyId()
						->keytext('title','优惠券名称')
						->keySingleImage('img','优惠券图片')
						->keyInteger('publish_cnt','总发放数量')
						->keyInteger('discount','优惠金额','单位：分')
						->keySelect('duration','有效期','',array('0'=>'永久有效','86400'=>'一天内有效','604800'=>'一周内有效','2592000'=>'一月内有效'))
						->keyMultiInput('max_cnt_enable|max_cnt','领取限制','每个用户最多允许领取多少张',array(array('type'=>'select','opt'=>array('不限制','限制'),'style'=>'width:95px;margin-right:5px'),array('type'=>'text','style'=>'width:95px;margin-right:5px')))
						->keyMultiInput('max_cnt_day_enable|max_cnt_day','领取限制','每个用户每天最多允许领取多少张',array(array('type'=>'select','opt'=>array('不限制','限制'),'style'=>'width:95px;margin-right:5px'),array('type'=>'text','style'=>'width:95px;margin-right:5px')))
						->keyMultiInput('min_price_enable|min_price','使用限制','最低可以使用的价格（单位：分），即满多少可用',array(array('type'=>'select','opt'=>array('不限制','限制'),'style'=>'width:95px;margin-right:5px'),array('type'=>'text','style'=>'width:95px;margin-right:5px')))
						->keySelect('valuation','类型','',array('现金券','折扣券'))
						->keyEditor('brief','优惠券说明')
						->keyCreateTime()
						->data($coupon)
						->buttonSubmit(U('shop/coupon',array('action'=>'add')))
						->buttonBack()
						->display();
				}
				break;
			case 'delete':
				$ids= I('ids');
				$ret = $this->coupon_model->delete_coupon($ids);
				if ($ret)
				{
					$this->success('操作成功。', U('shop/coupon'));
				}
				else
				{
					$this->error('操作失败。');
				}
				break;
			case 'couponlink':
				$id = I('id');
				$id = $this->coupon_model->encrypt_id($id);
				redirect(U('Udriver/index/get_coupon',array('id'=>$id)));//优惠券id 加密 跳转 具体链接 依业务需求修改
				break;
			default:
				$option['page'] = I('page',1);
				$option['r'] = I('r',10);
				$option['id'] = I('id');
				$coupon = $this->coupon_model->get_coupon_lsit($option);
//				empty($coupon['list'])
//					||
//				array_walk($coupon['list'],
//					function(&$a){
////						$a['link'] = think_encrypt($a['id'],'Coupon',0);
//						$a['link'] = \Think\Crypt\Driver\Des::encrypt($a['id'],md5('Coupon'),0);
//
//						$a['link'] = urlencode(base64_encode($a['link']));
////						var_dump(__file__.' line:'.__line__,$a['link']);exit;
//					});
				$totalCount = $coupon['count'];
				$builder = new AdminListBuilder();
				$builder
					->title('优惠券')
					->buttonnew(U('shop/coupon',array('action'=>'add')),'新增优惠券')
					->ajaxButton(U('shop/coupon',array('action'=>'delete')),'','删除')
					->keyText('id','优惠券id')
					->keyText('title','优惠券名称')
					->keyImage('img','优惠券图片')
					->keyMap('valuation','类型',array('现金券','折扣券'))
					->keyTruncText('brief','优惠券说明','25')
					->keyText('used_cnt','已发放数量')
					->keyText('publish_cnt','总发放数量')
					->keyTime('create_time','创建时间')
					->keyLinkByFlag('','领取链接','admin/shop/coupon/action/couponlink/id/###','id')
					->keyMap('duration','有效期',array('0'=>'永久有效','86400'=>'一天内有效','604800'=>'一周内有效','2592000'=>'一月内有效'))
					->keyDoAction('admin/shop/coupon/action/add/id/###','查看和编辑')
					->data($coupon['list'])
					->pagination($totalCount, $option['r'])
					->display();
				break;
		}
	}

	/*
	 * 优惠券领取情况
	 */
	public function user_coupon($action = '')
	{
		switch($action)
		{

			case 'add':
				//派优惠券
				if(IS_POST)
				{
					$coupon_id       = I('coupon_id', '', 'intval');
					$uid     = I('uid', '', 'trim');
					if(empty($coupon_id) || !($coupon = $this->coupon_model->get_coupon_by_id($coupon_id)))
						$this->error('请选择一个优惠券');
					if(empty($uid)) $this->error('请选择一个用户');
					$ret =$this->coupon_logic->add_a_coupon_to_user($coupon_id,$uid);
					if($ret)
					{
						$this->success('操作成功。', U('shop/user_coupon'));
					}
					else
					{
						$this->error('操作失败。'.$this->coupon_logic->error_str);
					}
				}
				else
				{
					$all_coupon_select = $this->coupon_model->getfield('id,title');
					if(empty($all_coupon_select))
					{
						redirect(U('shop/coupon',array('action'=>'add')));
					}
//					var_dump(__file__.' line:'.__line__,$user_list);exit;
					$builder       = new AdminConfigBuilder();
					$builder
						->title('手动发放优惠券')
						->keySelect('coupon_id','优惠券','要发放的优惠券',$all_coupon_select)
						->keyInteger('uid','用户id','')

						->buttonSubmit(U('shop/user_coupon',array('action'=>'add')))
						->buttonBack()
						->display();
				}


				break;
			case 'delete':
				$ids= I('ids');
				$ret = $this->user_coupon_model->delete_user_coupon($ids);
				if ($ret)
				{
					$this->success('操作成功。', U('shop/user_coupon'));
				}
				else
				{
					$this->error('操作失败。');
				}
				break;
			default:
				$option['id'] = I('id');
				$option['page'] = I('page',1);
				$option['r'] = I('r',10);
				$user_coupon = $this->user_coupon_model->get_user_coupon_list($option);

				empty($user_coupon['list']) ||
				array_walk($user_coupon['list'],
					function(&$a){
						$a['coupon_title'] = (empty($a['info']['title'])?'':$a['info']['title']);
						$a['coupon_img'] = (empty($a['info']['img'])?'':$a['info']['img']);
						$a['coupon_valuation'] = (empty($a['info']['valuation'])?'':$a['info']['valuation']);
						$a['coupon_discount'] = (empty($a['info']['rule']['discount'])?'':$a['info']['rule']['discount']);
						$a['coupon_min_price'] = (empty($a['info']['rule']['min_price'])?'':$a['info']['rule']['min_price']);
					});
				$totalCount = $user_coupon['count'];
//				var_dump(__file__.' line:'.__line__,$user_coupon['list']);exit;

				$builder = new AdminListBuilder();
				$builder
					->title('已领取优惠券')
					->buttonnew(U('shop/user_coupon',array('action'=>'add')),'派发优惠券')
					->ajaxButton(U('shop/user_coupon',array('action'=>'delete')),'','删除')
					->keyId()
					->keyText('user_id','用户id')
//					->keyText('coupon_title','优惠劵名称')
					->keyLinkByFlag('coupon_title','优惠券','admin/shop/coupon/id/###','coupon_id')
					->keyImage('coupon_img','优惠券图片')
					->keytext('coupon_discount','折扣,单位:分')
					->keytext('coupon_min_price','满多少可用,单位:分')
					->keyTime('create_time','发放时间')
					->keyTime('expire_time','到期时间')
					->keyLinkByFlag('order_id','订单号（无）','admin/shop/order/key/###','order_id')
					->keyMap('status','状态',array('0'=>'未使用','1'=>'已使用','2'=>'已过期'))

					->data($user_coupon['list'])
					->pagination($totalCount, $option['r'])
					->display();
				break;
		}
	}

  
	/*
	 *商品评论
	 */
	public function product_comment($action ='')
	{
		switch($action)
		{
			case 'edit_status':
				if(IS_POST)
				{
					$ids  =  I('ids');
					$status  =  I('get.status','','/[012]/');
					if(empty($ids) || empty($status))
					{
						$this->error('参数错误');
					}
					$ret = $this->product_comment_model->edit_status_product_comment($ids,$status);
					if($ret)
					{
						$this->success('操作成功');
					}
					else
					{
						$this->error('操作失败');
					}
				}
				break;
			case 'show_pic':
				$id = I('id','','intval');
				$ret = $this->product_comment_model->find($id);
				$this->assign('product_comment',$ret);
//				var_dump(__file__.' line:'.__line__,$ret);exit;
				$this->display('Shop@Shop/show_pic');
				break;
			default:
				$option['page'] = I('page','1','intval');
				$option['r'] = I('r','10','intval');
				$product_comment  = $this->product_comment_model->get_product_comment_list($option);
				$builder = new AdminListBuilder();
				$builder
					->title('商品评论管理')
					->ajaxButton(U('shop/product_comment',array('action'=>'edit_status','status'=>1)),'','审核通过')
					->ajaxButton(U('shop/product_comment',array('action'=>'edit_status','status'=>2)),'','审核不通过')
					->keyId()
					->keyJoin('product_id','商品','id','title','shop_product','/admin/shop/product')
					->keyJoin('order_id','订单','id','id','shop_order','/admin/shop/order')
					->keyJoin('user_id','用户','uid','nickname','member','/admin/user/index')
					->keyText('score','星数')
					->keyText('brief','评论内容')
					->keyTime('create_time','评论时间')
					->keyMap('status','状态',array('0'=>'未审核','1'=>'已通过','2'=>'未通过'))
//					->keyDoActionModalPopup('admin/shop/product_comment/action/show_pic/id/###','查看评论图片','操作')
					->data($product_comment['list'])
					->pagination($product_comment['count'], $option['r'])
					->display();
				break;
		}

	}

	///*商品属性开始

    /**商品属性列表
     * @param null $id
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function productNatureList($page = 1, $r = 20)
    {
        $title = I('title');
        $map['status'] = array('egt', 0);
        if (is_numeric($title)) {
            $map['id|title'] = array(intval($title), array('like', '%' . $title . '%'), '_multi' => true);
        } else {
            $map['title'] = array('like', '%' . (string)$title . '%');
        }
        $list = M('Shop_product')->where($map)->order('create_time desc')->page($page, $r)->select();
        $totalCount = M('Shop_product')->where($map)->count();
        int_to_string($list);
        //商品属性信息查询
        $map_profile['status'] = 1;
        $field_group = D('shop_nature_group')->where($map_profile)->select();
        $field_group_ids = array_column($field_group, 'id');
        $map_profile['profile_group_id'] = array('in', $field_group_ids);
        $fields_list = D('shop_nature_field')->where($map_profile)->getField('id,field_name,form_type,form_default_value');
        $fields_list = array_combine(array_column($fields_list, 'field_name'), $fields_list);
        $fields_list = array_slice($fields_list, 0, 8);//取出前8条，商品属性信息默认显示8条
        foreach ($list as &$tkl) {
            $map_field['product_id'] = $tkl['id'];
            foreach ($fields_list as $key => $val) {
                $map_field['field_id'] = $val['id'];
                $info = D('shop_nature_info')->where($map_field)->getField('field_data');
                if ($info == null || $info == '') {
                    $tkl[$key] = '';
                } else {
                	$default_value = explode("|",$val['form_default_value']);
                	if(in_array($val['form_type'],array("radio","select","checkbox"))){
            	    	$ex_data = explode(",",$info);
            	    	$len = count($ex_data);
            	    	$info = "";
            	    	for($i=0;$i<$len;$i++){
            	    		if(isset($ex_data[$i])) $info .= $default_value[$ex_data[$i]];
            	    		if($i != $len - 1) $info .= ",";
            	    	}
                	}
                	else if($val['form_type'] == "time"){
                		$info = date('Y-m-d H:i',$info);
                	}
                	$tkl[$key] = $info;
                }
            }
        }
        $builder = new AdminListBuilder();
        $builder->title(L('商品属性列表'));
        $builder->meta_title = L('商品属性列表');
        $builder->setSearchPostUrl(U('Admin/Shop/productNatureList'))->search(L('_SEARCH_'), 'title', 'text', L('_PLACEHOLDER_NICKNAME_ID_'));
        $builder->keyId()->keyText('title', L('商品名称'));
        foreach ($fields_list as $vt) {
            $builder->keyText($vt['field_name'], $vt['field_name']);
        }
        $builder->keyDoAction('Shop/prodoctNatureDetails?id=###','编辑')->data($list);
        $builder->pagination($totalCount, $r);
        $builder->display();
    }


    /**商品属性详情
     * @param string $id
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function prodoctNatureDetails($id = 0)
    {
    	if(!$id){
    		$this->error("未找到商品ID，请重试。");
    	}
    	$map_profile['status'] = 1;
    	$field_group = D('shop_nature_group')->where($map_profile)->select();
    	$field_group_ids = array_column($field_group, 'id');
    	$map_profile['profile_group_id'] = array('in', $field_group_ids);
    	$fields_list = D('shop_nature_field')->where($map_profile)->getField('id,field_name,form_type,form_default_value');
    	$fields_list = array_combine(array_column($fields_list, 'field_name'), $fields_list);

        if (IS_POST) {
        	$data = I('post.');
         	foreach($fields_list as $val){
         		$map['product_id'] = $id;
         		$map['field_id'] = $val['id'];
         		$info = D('Shop_nature_info')->where($map)->find();
         		if($info && $info['field_data'] != $data[$val['id']]){
         			$map_data['field_data'] = $data[$val['id']];
         			$map_data['changeTime'] = time();
         			$res = D('Shop_nature_info')->where($map)->save($map_data);
         		}
         		else if(!$info){
         			$map['field_data'] = $data[$val['id']];
         			$map['createTime'] = time();
         			$res = D('shop_nature_info')->add($map);
         		}
         		
         	}
         	if ($res) {
            	$this->success(L('_SUCCESS_OPERATE_').L('_EXCLAMATION_'));
        	}else {
                $this->error("没有更改信息或出现错误，请重试。");
            }
        } else {
            $map['id'] = $id;
            $map['status'] = array('egt', 0);
            $product = M('Shop_product')->where($map)->find();
            //扩展信息查询
            $map_field['product_id'] = $product['id'];
            foreach ($fields_list as $val) {
                $map_field['field_id'] = $val['id'];
                $field_data = D('shop_nature_info')->where($map_field)->getField('field_data');
                if ($field_data == null || $field_data == '') {
                    $product[$val['id']] = '';
                } else {
                    $product[$val['id']] = $field_data;
                }
                $product[$val['id']] = $field_data;
            }
            $builder = new AdminConfigBuilder();
            $builder->title(L('商品属性详情'));
            $builder->meta_title = L('商品属性详情');
            $builder->keyId()->keyReadOnly('title', L('商品名称'));
            foreach ($fields_list as $vt) {
            	$value = explode("|",$vt['form_default_value']);
                switch($vt['form_type']){
                	case "input":
                		$builder->keyText($vt['id'], $vt['field_name']);
                		break;
                	case "radio":
                		$builder->keyRadio($vt['id'], $vt['field_name'],"",$value);
                		break;
                	case "checkbox":
                		$builder->keyCheckBox($vt['id'], $vt['field_name'],"",$value);
                		break;
                	case "select":
                		$builder->keySelect($vt['id'], $vt['field_name'],"",$value);
                		break;
                	case "time":
                		$builder->keyTime($vt['id'], $vt['field_name']);
                		break;
                	case "textarea":
                		$builder->keyTextArea($vt['id'], $vt['field_name']);
                		break;
                }
            }

            $builder->data($product);
            $builder->buttonSubmit('', L('_SAVE_'));
            $builder->buttonBack();
            $builder->display();
        }

    }

    /**商品属性管理页
     * @param 
     * @author 郑钟良<zzl@ourstu.com>
     * @version 20160617
     */
    public function productNatureManage($page = 1, $r = 20)
    {
        $map['status'] = array('egt', 0);
        $profileList = D('shop_nature_group')->where($map)->order("sort asc")->page($page, $r)->select();
        $totalCount = D('shop_nature_group')->where($map)->count();
        $builder = new AdminListBuilder();
        $builder->title("商品属性管理页面");
        $builder->meta_title = L('商品属性管理');
        $builder->buttonNew(U('editNatureGroup', array('id' => '0')))->buttonDelete(U('natureGroupStatus', array('status' => '-1')))->setStatusUrl(U('natureGroupStatus'))->buttonSort(U('sortNatureGroup'));
        $builder->keyId()->keyText('profile_name', L('_GROUP_NAME_'))->keyText('sort', L('_SORT_'))->keyTime("createTime", L('_CREATE_TIME_'))->keyBool('visiable', L('_PUBLIC_IF_'));
        $builder->keyStatus()->keyDoAction('Shop/field?id=###', L('_FIELD_MANAGER_'))->keyDoAction('Shop/editNatureGroup?id=###', L('_EDIT_'));
        $builder->data($profileList);
        $builder->pagination($totalCount, $r);
        $builder->display();
    }

    /**属性分组排序
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function sortNatureGroup($ids = null)
    {
        if (IS_POST) {
            $builder = new AdminSortBuilder();
            $builder->doSort('Shop_nature_group', $ids);
        } else {
            $map['status'] = array('egt', 0);
            $list = D('shop_nature_group')->where($map)->order("sort asc")->select();
            foreach ($list as $key => $val) {
                $list[$key]['title'] = $val['profile_name'];
            }
            $builder = new AdminSortBuilder();
            $builder->meta_title = L('属性分组排序');
            $builder->data($list);
            $builder->buttonSubmit(U('sortNatureGroup'))->buttonBack();
            $builder->display();
        }
    }

    /**商品属性字段列表
     * @param $id
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function field($id, $page = 1, $r = 20)

    {
        $profile = D('shop_nature_group')->where('id=' . $id)->find();
        $map['status'] = array('egt', 0);
        $map['profile_group_id'] = $id;
        $field_list = D('shop_nature_field')->where($map)->order("sort asc")->page($page, $r)->select();
        $totalCount = D('shop_nature_field')->where($map)->count();
        $type_default = array(
            'input' => L('_ONE-WAY_TEXT_BOX_'),
            'radio' => L('_RADIO_BUTTON_'),
            'checkbox' => L('_CHECKBOX_'),
            'select' => L('_DROP-DOWN_BOX_'),
            'time' => L('_DATE_'),
            'textarea' => L('_MULTI_LINE_TEXT_BOX_')
        );
        $child_type = array(
            'string' => L('_STRING_'),
            'phone' => L('_PHONE_NUMBER_'),
            'email' => L('_MAILBOX_'),
            'number' => L('_NUMBER_'),
            'join' => L('_RELATED_FIELD_')
        );
        foreach ($field_list as &$val) {
            $val['form_type'] = $type_default[$val['form_type']];
            $val['child_form_type'] = $child_type[$val['child_form_type']];
        }
        $builder = new AdminListBuilder();
        $builder->title('【' . $profile['profile_name'] . '】 字段管理');
        $builder->meta_title = $profile['profile_name'] . L('_FIELD_MANAGEMENT_');
        $builder->buttonNew(U('editFieldSetting', array('id' => '0', 'profile_group_id' => $id)))->buttonDelete(U('setFieldSettingStatus', array('status' => '-1')))->setStatusUrl(U('setFieldSettingStatus'))->buttonSort(U('sortField', array('id' => $id)))->button(L('_RETURN_'), array('href' => U('productNatureManage')));
        $builder->keyId()->keyText('field_name', L('_FIELD_NAME_'))->keyBool('visiable', L('_OPEN_YE_OR_NO_'))->keyBool('required', L('_WHETHER_THE_REQUIRED_'))->keyText('sort', L('_SORT_'))->keyText('form_type', L('_FORM_TYPE_'))->keyText('child_form_type', L('_TWO_FORM_TYPE_'))->keyText('form_default_value', L('_DEFAULT_'))->keyText('validation', L('_FORM_VERIFICATION_MODE_'))->keyText('input_tips', L('_USER_INPUT_PROMPT_'));
        $builder->keyTime("createTime", L('_CREATE_TIME_'))->keyStatus()->keyDoAction('Shop/editFieldSetting?profile_group_id=' . $id . '&id=###', L('_EDIT_'));
        $builder->data($field_list);
        $builder->pagination($totalCount, $r);
        $builder->display();
    }

    /**字段排序
     * @param $id
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function sortField($id = '', $ids = null)
    {
        if (IS_POST) {
            $builder = new AdminSortBuilder();
            $builder->doSort('Shop_nature_field', $ids);
        } else {
            $profile = D('shop_nature_group')->where('id=' . $id)->find();
            $map['status'] = array('egt', 0);
            $map['profile_group_id'] = $id;
            $list = D('shop_nature_field')->where($map)->order("sort asc")->select();
            foreach ($list as $key => $val) {
                $list[$key]['title'] = $val['field_name'];
            }
            $builder = new AdminSortBuilder();
            $builder->meta_title = $profile['profile_name'] . L('_FIELD_SORT_');
            $builder->data($list);
            $builder->buttonSubmit(U('sortField'))->buttonBack();
            $builder->display();
        }
    }

    /**添加、编辑字段信息
     * @param $id
     * @param $profile_group_id
     * @param $field_name
     * @param $child_form_type
     * @param $visiable
     * @param $required
     * @param $form_type
     * @param $form_default_value
     * @param $validation
     * @param $input_tips
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function editFieldSetting($id = 0, $profile_group_id = 0, $field_name = '', $child_form_type = 0, $visiable = 0, $required = 0, $form_type = 0, $form_default_value = '', $validation = 0, $input_tips = '')
    {
        if (IS_POST) {
            $data['field_name'] = $field_name;
            if ($data['field_name'] == '') {
                $this->error(L('_FIELD_NAME_CANNOT_BE_EMPTY_'));
            }
            $data['profile_group_id'] = $profile_group_id;
            $data['visiable'] = $visiable;
            $data['required'] = $required;
            $data['form_type'] = $form_type;
            $data['form_default_value'] = $form_default_value;
            //当表单类型为以下三种是默认值不能为空判断@MingYang
            $form_types = array('radio', 'checkbox', 'select');
            if (in_array($data['form_type'], $form_types)) {
                if ($data['form_default_value'] == '') {
                    $this->error($data['form_type'] . L('_THE_DEFAULT_VALUE_OF_THE_FORM_TYPE_CAN_NOT_BE_EMPTY_'));
                }
            }
            $data['input_tips'] = $input_tips;
            //增加当二级字段类型为join时也提交$child_form_type @MingYang
            if ($form_type == 'input') {
                $data['child_form_type'] = $child_form_type;
            } else {
                $data['child_form_type'] = '';
            }
            $data['validation'] = $validation;
            if ($id != '') {
                $res = D('shop_nature_field')->where('id=' . $id)->save($data);
            } else {
                $map['field_name'] = $field_name;
                $map['status'] = array('egt', 0);
                $map['profile_group_id'] = $profile_group_id;
                if (D('shop_nature_field')->where($map)->count() > 0) {
                    $this->error(L('_THIS_GROUP_ALREADY_HAS_THE_SAME_NAME_FIELD_PLEASE_USE_ANOTHER_NAME_'));
                }
                $data['status'] = 1;
                $data['createTime'] = time();
                $data['sort'] = 0;
                $res = D('shop_nature_field')->add($data);
            }
            $this->success($id == '' ? L('_ADD_FIELD_SUCCESS_') : L('_EDIT_FIELD_SUCCESS_'), U('field', array('id' => $profile_group_id)));
        } else {
            $builder = new AdminConfigBuilder();
            if ($id != 0) {
                $shop_nature_field = D('shop_nature_field')->where('id=' . $id)->find();

                $builder->title(L('_MODIFY_FIELD_INFORMATION_'));
                $builder->meta_title = L('_MODIFY_FIELD_INFORMATION_');
            } else {
                $builder->title(L('_ADD_FIELD_'));
                $builder->meta_title = L('_NEW_FIELD_');
                $shop_nature_field['profile_group_id'] = $profile_group_id;
                $shop_nature_field['visiable'] = 1;
                $shop_nature_field['required'] = 1;
            }
            $type_default = array(
                'input' => L('_ONE-WAY_TEXT_BOX_'),
                'radio' => L('_RADIO_BUTTON_'),
                'checkbox' => L('_CHECKBOX_'),
                'select' => L('_DROP-DOWN_BOX_'),
                'time' => L('_DATE_'),
                'textarea' => L('_MULTI_LINE_TEXT_BOX_')
            );
            $child_type = array(
                'string' => L('_STRING_'),
                'phone' => L('_PHONE_NUMBER_'),
                'email' => L('_MAILBOX_'),
                //增加可选择关联字段类型 @MingYang
                'join' => L('_RELATED_FIELD_'),
                'number' => L('_NUMBER_')
            );
            $builder->keyReadOnly("id", L('_LOGO_'))->keyReadOnly('profile_group_id', L('_GROUP_ID_'))->keyText('field_name', L('_FIELD_NAME_'))->keySelect('form_type', L('_FORM_TYPE_'), '', $type_default)->keySelect('child_form_type', L('_TWO_FORM_TYPE_'), '', $child_type)->keyTextArea('form_default_value', "默认值。多个值用'|'分割开,示例：【字符串：男|女，数组：1:男|2:女，关联数据表：字段名|表名】")
                ->keyText('validation', L('_FORM_VALIDATION_RULES_'), '例：min=5&max=10')->keyText('input_tips', L('_USER_INPUT_PROMPT_'), L('_PROMPTS_THE_USER_TO_ENTER_THE_FIELD_INFORMATION_'))->keyBool('visiable', L('_OPEN_YE_OR_NO_'))->keyBool('required', L('_WHETHER_THE_REQUIRED_'));
            $builder->data($shop_nature_field);
            $builder->buttonSubmit(U('editFieldSetting'), $id == 0 ? L('_ADD_') : L('_MODIFY_'))->buttonBack();
            $builder->display();
        }

    }

    /**设置字段状态：删除=-1，禁用=0，启用=1
     * @param $ids
     * @param $status
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function setFieldSettingStatus($ids, $status)
    {
        $builder = new AdminListBuilder();
        $builder->doSetStatus('shop_nature_field', $ids, $status);
    }

    /**设置分组状态：删除=-1，禁用=0，启用=1
     * @param $status
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function natureGroupStatus($status)
    {
        $id = array_unique((array)I('ids', 0));
        if ($id[0] == 0) {
            $this->error(L('_PLEASE_CHOOSE_TO_OPERATE_THE_DATA_'));
        }
        $id = is_array($id) ? $id : explode(',', $id);
        D('shop_nature_group')->where(array('id' => array('in', $id)))->setField('status', $status);
        if ($status == -1) {
            $this->success(L('_DELETE_SUCCESS_'));
        } else if ($status == 0) {
            $this->success(L('_DISABLE_SUCCESS_'));
        } else {
            $this->success(L('_ENABLE_SUCCESS_'));
        }

    }

    /**添加、编辑属性分组信息
     * @param $id
     * @param $profile_name
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function editNatureGroup($id = 0, $profile_name = '', $visiable = 1)
    {
        if (IS_POST) {
            $data['profile_name'] = $profile_name;
            $data['visiable'] = $visiable;
            if ($data['profile_name'] == '') {
                $this->error(L('_GROUP_NAME_CANNOT_BE_EMPTY_'));
            }
            if ($id != '') {
                $res = D('shop_nature_group')->where('id=' . $id)->save($data);
            } else {
                $map['profile_name'] = $profile_name;
                $map['status'] = array('egt', 0);
                if (D('shop_nature_group')->where($map)->count() > 0) {
                    $this->error(L('_ALREADY_HAS_THE_SAME_NAME_GROUP_PLEASE_USE_THE_OTHER_GROUP_NAME_'));
                }
                $data['status'] = 1;
                $data['createTime'] = time();
                $res = D('shop_nature_group')->add($data);
            }
            if ($res) {
                $this->success($id == '' ? L('_ADD_GROUP_SUCCESS_') : L('_EDIT_GROUP_SUCCESS_'), U('productNatureManage'));
            } else {
                $this->error($id == '' ? L('_ADD_GROUP_FAILURE_') : L('_EDIT_GROUP_FAILED_'));
            }
        } else {
            $builder = new AdminConfigBuilder();
            if ($id != 0) {
                $profile = D('shop_nature_group')->where('id=' . $id)->find();
                $builder->title(L('_MODIFIED_GROUP_INFORMATION_'));
                $builder->meta_title = L('_MODIFIED_GROUP_INFORMATION_');
            } else {
                $builder->title(L('新增属性分组'));
                $builder->meta_title = L('新增属性分组');
            }
            $builder->keyReadOnly("id", L('_LOGO_'))->keyText('profile_name', L('_GROUP_NAME_'))->keyBool('visiable', L('_OPEN_YE_OR_NO_'));
            $builder->data($profile);
            $builder->buttonSubmit(U('editNatureGroup'), $id == 0 ? L('_ADD_') : L('_MODIFY_'))->buttonBack();
            $builder->display();
        }

    }
    //商品属性结束*/

    ///*分类属性开始

    /**分类属性列表
     * @param null $id
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function cateAttrList($page = 1, $r = 20)
    {
        $title = I('title');
        $map['status'] = array('egt', 0);
        if (is_numeric($title)) {
            $map['id|title'] = array(intval($title), array('like', '%' . $title . '%'), '_multi' => true);
        } else {
            $map['title'] = array('like', '%' . (string)$title . '%');
        }
        $list = M('Shop_product_cats')->where($map)->order('create_time desc')->page($page, $r)->select();
        $totalCount = M('Shop_product_cats')->where($map)->count();
        int_to_string($list);
        //分类属性信息查询
        $map_profile['status'] = 1;
        $field_group = D('shop_attr_group')->where($map_profile)->select();
        $field_group_ids = array_column($field_group, 'id');
        $map_profile['profile_group_id'] = array('in', $field_group_ids);
        $fields_list = D('shop_attr_field')->where($map_profile)->getField('id,field_name,form_type,form_default_value');
        $fields_list = array_combine(array_column($fields_list, 'field_name'), $fields_list);
        $fields_list = array_slice($fields_list, 0, 8);//取出前8条，分类属性信息默认显示8条
        foreach ($list as &$tkl) {
            $map_field['cate_id'] = $tkl['id'];
            foreach ($fields_list as $key => $val) {
                $map_field['field_id'] = $val['id'];
                $info = D('shop_attr_info')->where($map_field)->getField('field_data');
                if ($info == null || $info == '') {
                    $tkl[$key] = '';
                } else {
                    $default_value = explode("|",$val['form_default_value']);
                    if(in_array($val['form_type'],array("radio","select","checkbox"))){
                        $ex_data = explode(",",$info);
                        $len = count($ex_data);
                        $info = "";
                        for($i=0;$i<$len;$i++){
                            if(isset($ex_data[$i])) $info .= $default_value[$ex_data[$i]];
                            if($i != $len - 1) $info .= ",";
                        }
                    }
                    else if($val['form_type'] == "time"){
                        $info = date('Y-m-d H:i',$info);
                    }
                    $tkl[$key] = $info;
                }
            }
        }
        $builder = new AdminListBuilder();
        $builder->title(L('分类属性列表'));
        $builder->meta_title = L('分类属性列表');
        $builder->setSearchPostUrl(U('Admin/Shop/cateAttrList'))->search(L('_SEARCH_'), 'title', 'text', L('_PLACEHOLDER_NICKNAME_ID_'));
        $builder->keyId()->keyText('title', L('分类名称'));
        foreach ($fields_list as $vt) {
            $builder->keyText($vt['field_name'], $vt['field_name']);
        }
        $builder->keyDoAction('Shop/cateAttrDetails?id=###','编辑')->data($list);
        $builder->pagination($totalCount, $r);
        $builder->display();
    }


    /**分类属性详情
     * @param string $id
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function cateAttrDetails($id = 0)
    {
        if(!$id){
            $this->error("未找到分类ID，请重试。");
        }
        $map_profile['status'] = 1;
        $field_group = D('shop_attr_group')->where($map_profile)->select();
        $field_group_ids = array_column($field_group, 'id');
        $map_profile['profile_group_id'] = array('in', $field_group_ids);
        $fields_list = D('shop_attr_field')->where($map_profile)->getField('id,field_name,form_type,form_default_value');
        $fields_list = array_combine(array_column($fields_list, 'field_name'), $fields_list);

        if (IS_POST) {
            $data = I('post.');
            foreach($fields_list as $val){
                $map['cate_id'] = $id;
                $map['field_id'] = $val['id'];
                $info = D('Shop_attr_info')->where($map)->find();
                if($info && $info['field_data'] != $data[$val['id']]){
                    $map_data['field_data'] = $data[$val['id']];
                    $map_data['changeTime'] = time();
                    $res = D('Shop_attr_info')->where($map)->save($map_data);
                }
                else if(!$info){
                    $map['field_data'] = $data[$val['id']];
                    $map['createTime'] = time();
                    $res = D('shop_attr_info')->add($map);
                }
                
            }
            if ($res) {
                $this->success(L('_SUCCESS_OPERATE_').L('_EXCLAMATION_'));
            }else {
                $this->error("没有更改信息或出现错误，请重试。");
            }
        } else {
            $map['id'] = $id;
            $map['status'] = array('egt', 0);
            $product_cats = M('Shop_product_cats')->where($map)->find();
            //扩展信息查询
            $map_field['cate_id'] = $product_cats['id'];
            foreach ($fields_list as $val) {
                $map_field['field_id'] = $val['id'];
                $field_data = D('shop_attr_info')->where($map_field)->getField('field_data');
                if ($field_data == null || $field_data == '') {
                    $product_cats[$val['id']] = '';
                } else {
                    $product_cats[$val['id']] = $field_data;
                }
                $product_cats[$val['id']] = $field_data;
            }
            $builder = new AdminConfigBuilder();
            $builder->title(L('分类属性详情'));
            $builder->meta_title = L('分类属性详情');
            $builder->keyId()->keyReadOnly('title', L('分类名称'));
            foreach ($fields_list as $vt) {
                $value = explode("|",$vt['form_default_value']);
                switch($vt['form_type']){
                    case "input":
                        $builder->keyText($vt['id'], $vt['field_name']);
                        break;
                    case "radio":
                        $builder->keyRadio($vt['id'], $vt['field_name'],"",$value);
                        break;
                    case "checkbox":
                        $builder->keyCheckBox($vt['id'], $vt['field_name'],"",$value);
                        break;
                    case "select":
                        $builder->keySelect($vt['id'], $vt['field_name'],"",$value);
                        break;
                    case "time":
                        $builder->keyTime($vt['id'], $vt['field_name']);
                        break;
                    case "textarea":
                        $builder->keyTextArea($vt['id'], $vt['field_name']);
                        break;
                }
            }

            $builder->data($product_cats);
            $builder->buttonSubmit('', L('_SAVE_'));
            $builder->buttonBack();
            $builder->display();
        }

    }

    /**分类属性管理页
     * @param 
     * @author 郑钟良<zzl@ourstu.com>
     * @version 20160617
     */
    public function cateAttrManage($page = 1, $r = 20)
    {
        $map['status'] = array('egt', 0);
        $profileList = D('shop_attr_group')->where($map)->order("sort asc")->page($page, $r)->select();
        $totalCount = D('shop_attr_group')->where($map)->count();
        $builder = new AdminListBuilder();
        $builder->title("分类属性管理页面");
        $builder->meta_title = L('分类属性管理');
        $builder->buttonNew(U('editAttrGroup', array('id' => '0')))->buttonDelete(U('attrGroupStatus', array('status' => '-1')))->setStatusUrl(U('attrGroupStatus'))->buttonSort(U('sortAttrGroup'));
        $builder->keyId()->keyText('profile_name', L('_GROUP_NAME_'))->keyText('sort', L('_SORT_'))->keyTime("createTime", L('_CREATE_TIME_'))->keyBool('visiable', L('_PUBLIC_IF_'));
        $builder->keyStatus()->keyDoAction('Shop/attrField?id=###', L('_FIELD_MANAGER_'))->keyDoAction('Shop/editAttrGroup?id=###', L('_EDIT_'));
        $builder->data($profileList);
        $builder->pagination($totalCount, $r);
        $builder->display();
    }

    /**分类属性分组排序
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function sortAttrGroup($ids = null)
    {
        if (IS_POST) {
            $builder = new AdminSortBuilder();
            $builder->doSort('Shop_attr_group', $ids);
        } else {
            $map['status'] = array('egt', 0);
            $list = D('shop_attr_group')->where($map)->order("sort asc")->select();
            foreach ($list as $key => $val) {
                $list[$key]['title'] = $val['profile_name'];
            }
            $builder = new AdminSortBuilder();
            $builder->meta_title = L('分类属性分组排序');
            $builder->data($list);
            $builder->buttonSubmit(U('sortAttrGroup'))->buttonBack();
            $builder->display();
        }
    }

    /**分类属性字段列表
     * @param $id
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function attrField($id, $page = 1, $r = 20)

    {
        $profile = D('shop_attr_group')->where('id=' . $id)->find();
        $map['status'] = array('egt', 0);
        $map['profile_group_id'] = $id;
        $field_list = D('shop_attr_field')->where($map)->order("sort asc")->page($page, $r)->select();
        $totalCount = D('shop_attr_field')->where($map)->count();
        $type_default = array(
            'input' => L('_ONE-WAY_TEXT_BOX_'),
            'radio' => L('_RADIO_BUTTON_'),
            'checkbox' => L('_CHECKBOX_'),
            'select' => L('_DROP-DOWN_BOX_'),
            'time' => L('_DATE_'),
            'textarea' => L('_MULTI_LINE_TEXT_BOX_')
        );
        $child_type = array(
            'string' => L('_STRING_'),
            'phone' => L('_PHONE_NUMBER_'),
            'email' => L('_MAILBOX_'),
            'number' => L('_NUMBER_'),
            'join' => L('_RELATED_FIELD_')
        );
        foreach ($field_list as &$val) {
            $val['form_type'] = $type_default[$val['form_type']];
            $val['child_form_type'] = $child_type[$val['child_form_type']];
        }
        $builder = new AdminListBuilder();
        $builder->title('【' . $profile['profile_name'] . '】 字段管理');
        $builder->meta_title = $profile['profile_name'] . L('_FIELD_MANAGEMENT_');
        $builder->buttonNew(U('editAttrField', array('id' => '0', 'profile_group_id' => $id)))->buttonDelete(U('setAttrFieldStatus', array('status' => '-1')))->setStatusUrl(U('setAttrFieldStatus'))->buttonSort(U('sortAttrField', array('id' => $id)))->button(L('_RETURN_'), array('href' => U('cateAttrManage')));
        $builder->keyId()->keyText('field_name', L('_FIELD_NAME_'))->keyBool('visiable', L('_OPEN_YE_OR_NO_'))->keyBool('required', L('_WHETHER_THE_REQUIRED_'))->keyText('sort', L('_SORT_'))->keyText('form_type', L('_FORM_TYPE_'))->keyText('child_form_type', L('_TWO_FORM_TYPE_'))->keyText('form_default_value', L('_DEFAULT_'))->keyText('validation', L('_FORM_VERIFICATION_MODE_'))->keyText('input_tips', L('_USER_INPUT_PROMPT_'));
        $builder->keyTime("createTime", L('_CREATE_TIME_'))->keyStatus()->keyDoAction('Shop/editAttrField?profile_group_id=' . $id . '&id=###', L('_EDIT_'));
        $builder->data($field_list);
        $builder->pagination($totalCount, $r);
        $builder->display();
    }

    /**分类属性字段排序
     * @param $id
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function sortAttrField($id = '', $ids = null)
    {
        if (IS_POST) {
            $builder = new AdminSortBuilder();
            $builder->doSort('Shop_attr_field', $ids);
        } else {
            $profile = D('shop_attr_group')->where('id=' . $id)->find();
            $map['status'] = array('egt', 0);
            $map['profile_group_id'] = $id;
            $list = D('shop_attr_field')->where($map)->order("sort asc")->select();
            foreach ($list as $key => $val) {
                $list[$key]['title'] = $val['field_name'];
            }
            $builder = new AdminSortBuilder();
            $builder->meta_title = $profile['profile_name'] . L('_FIELD_SORT_');
            $builder->data($list);
            $builder->buttonSubmit(U('sortAttrField'))->buttonBack();
            $builder->display();
        }
    }

    /**添加、编辑分类属性字段信息
     * @param $id
     * @param $profile_group_id
     * @param $field_name
     * @param $child_form_type
     * @param $visiable
     * @param $required
     * @param $form_type
     * @param $form_default_value
     * @param $validation
     * @param $input_tips
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function editAttrField($id = 0, $profile_group_id = 0, $field_name = '', $child_form_type = 0, $visiable = 0, $required = 0, $form_type = 0, $form_default_value = '', $validation = 0, $input_tips = '')
    {
        if (IS_POST) {
            $data['field_name'] = $field_name;
            if ($data['field_name'] == '') {
                $this->error(L('_FIELD_NAME_CANNOT_BE_EMPTY_'));
            }
            $data['profile_group_id'] = $profile_group_id;
            $data['visiable'] = $visiable;
            $data['required'] = $required;
            $data['form_type'] = $form_type;
            $data['form_default_value'] = $form_default_value;
            //当表单类型为以下三种是默认值不能为空判断@MingYang
            $form_types = array('radio', 'checkbox', 'select');
            if (in_array($data['form_type'], $form_types)) {
                if ($data['form_default_value'] == '') {
                    $this->error($data['form_type'] . L('_THE_DEFAULT_VALUE_OF_THE_FORM_TYPE_CAN_NOT_BE_EMPTY_'));
                }
            }
            $data['input_tips'] = $input_tips;
            //增加当二级字段类型为join时也提交$child_form_type @MingYang
            if ($form_type == 'input') {
                $data['child_form_type'] = $child_form_type;
            } else {
                $data['child_form_type'] = '';
            }
            $data['validation'] = $validation;
            if ($id != '') {
                $res = D('shop_attr_field')->where('id=' . $id)->save($data);
            } else {
                $map['field_name'] = $field_name;
                $map['status'] = array('egt', 0);
                $map['profile_group_id'] = $profile_group_id;
                if (D('shop_attr_field')->where($map)->count() > 0) {
                    $this->error(L('_THIS_GROUP_ALREADY_HAS_THE_SAME_NAME_FIELD_PLEASE_USE_ANOTHER_NAME_'));
                }
                $data['status'] = 1;
                $data['createTime'] = time();
                $data['sort'] = 0;
                $res = D('shop_attr_field')->add($data);
            }
            $this->success($id == '' ? L('_ADD_FIELD_SUCCESS_') : L('_EDIT_FIELD_SUCCESS_'), U('attrField', array('id' => $profile_group_id)));
        } else {
            $builder = new AdminConfigBuilder();
            if ($id != 0) {
                $shop_attr_field = D('shop_attr_field')->where('id=' . $id)->find();

                $builder->title(L('_MODIFY_FIELD_INFORMATION_'));
                $builder->meta_title = L('_MODIFY_FIELD_INFORMATION_');
            } else {
                $builder->title(L('_ADD_FIELD_'));
                $builder->meta_title = L('_NEW_FIELD_');
                $shop_attr_field['profile_group_id'] = $profile_group_id;
                $shop_attr_field['visiable'] = 1;
                $shop_attr_field['required'] = 1;
            }
            $type_default = array(
                'input' => L('_ONE-WAY_TEXT_BOX_'),
                'radio' => L('_RADIO_BUTTON_'),
                'checkbox' => L('_CHECKBOX_'),
                'select' => L('_DROP-DOWN_BOX_'),
                'time' => L('_DATE_'),
                'textarea' => L('_MULTI_LINE_TEXT_BOX_')
            );
            $child_type = array(
                'string' => L('_STRING_'),
                'phone' => L('_PHONE_NUMBER_'),
                'email' => L('_MAILBOX_'),
                //增加可选择关联字段类型 @MingYang
                'join' => L('_RELATED_FIELD_'),
                'number' => L('_NUMBER_')
            );
            $builder->keyReadOnly("id", L('_LOGO_'))->keyReadOnly('profile_group_id', L('_GROUP_ID_'))->keyText('field_name', L('_FIELD_NAME_'))->keySelect('form_type', L('_FORM_TYPE_'), '', $type_default)->keySelect('child_form_type', L('_TWO_FORM_TYPE_'), '', $child_type)->keyTextArea('form_default_value', "默认值。多个值用'|'分割开,示例：【字符串：男|女，数组：1:男|2:女，关联数据表：字段名|表名】")
                ->keyText('validation', L('_FORM_VALIDATION_RULES_'), '例：min=5&max=10')->keyText('input_tips', L('_USER_INPUT_PROMPT_'), L('_PROMPTS_THE_USER_TO_ENTER_THE_FIELD_INFORMATION_'))->keyBool('visiable', L('_OPEN_YE_OR_NO_'))->keyBool('required', L('_WHETHER_THE_REQUIRED_'));
            $builder->data($shop_attr_field);
            $builder->buttonSubmit(U('editAttrField'), $id == 0 ? L('_ADD_') : L('_MODIFY_'))->buttonBack();
            $builder->display();
        }

    }

    /**设置分类属性字段状态：删除=-1，禁用=0，启用=1
     * @param $ids
     * @param $status
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function setAttrFieldStatus($ids, $status)
    {
        $builder = new AdminListBuilder();
        $builder->doSetStatus('shop_attr_field', $ids, $status);
    }

    /**设置分类属性分组状态：删除=-1，禁用=0，启用=1
     * @param $status
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function attrGroupStatus($status)
    {
        $id = array_unique((array)I('ids', 0));
        if ($id[0] == 0) {
            $this->error(L('_PLEASE_CHOOSE_TO_OPERATE_THE_DATA_'));
        }
        $id = is_array($id) ? $id : explode(',', $id);
        D('shop_attr_group')->where(array('id' => array('in', $id)))->setField('status', $status);
        if ($status == -1) {
            $this->success(L('_DELETE_SUCCESS_'));
        } else if ($status == 0) {
            $this->success(L('_DISABLE_SUCCESS_'));
        } else {
            $this->success(L('_ENABLE_SUCCESS_'));
        }

    }

    /**添加、编辑分类属性分组信息
     * @param $id
     * @param $profile_name
     * @author 郑钟良<zzl@ourstu.com>
     */
    public function editAttrGroup($id = 0, $profile_name = '', $visiable = 1)
    {
        if (IS_POST) {
            $data['profile_name'] = $profile_name;
            $data['visiable'] = $visiable;
            if ($data['profile_name'] == '') {
                $this->error(L('_GROUP_NAME_CANNOT_BE_EMPTY_'));
            }
            if ($id != '') {
                $res = D('shop_attr_group')->where('id=' . $id)->save($data);
            } else {
                $map['profile_name'] = $profile_name;
                $map['status'] = array('egt', 0);
                if (D('shop_attr_group')->where($map)->count() > 0) {
                    $this->error(L('_ALREADY_HAS_THE_SAME_NAME_GROUP_PLEASE_USE_THE_OTHER_GROUP_NAME_'));
                }
                $data['status'] = 1;
                $data['createTime'] = time();
                $res = D('shop_attr_group')->add($data);
            }
            if ($res) {
                $this->success($id == '' ? L('_ADD_GROUP_SUCCESS_') : L('_EDIT_GROUP_SUCCESS_'), U('cateAttrManage'));
            } else {
                $this->error($id == '' ? L('_ADD_GROUP_FAILURE_') : L('_EDIT_GROUP_FAILED_'));
            }
        } else {
            $builder = new AdminConfigBuilder();
            if ($id != 0) {
                $profile = D('shop_attr_group')->where('id=' . $id)->find();
                $builder->title(L('_MODIFIED_GROUP_INFORMATION_'));
                $builder->meta_title = L('_MODIFIED_GROUP_INFORMATION_');
            } else {
                $builder->title(L('新增分类属性分组'));
                $builder->meta_title = L('新增分类属性分组');
            }
            $builder->keyReadOnly("id", L('_LOGO_'))->keyText('profile_name', L('_GROUP_NAME_'))->keyBool('visiable', L('_OPEN_YE_OR_NO_'));
            $builder->data($profile);
            $builder->buttonSubmit(U('editAttrGroup'), $id == 0 ? L('_ADD_') : L('_MODIFY_'))->buttonBack();
            $builder->display();
        }

    }
    //分类属性结束*/
}
