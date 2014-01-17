<?php
//asms会员模型
class AsmsOrderModel extends RelationModel{
    protected $_link = array(
        'member'=> array( //关联会员表
        'mapping_type'=>HAS_ONE ,
        'class_name'=>'member',
        'foreign_key'=>'asms_member_id',
        'condition'=>'status=1',
        'mapping_fields'=>'id,username,name,status',
        // 定义更多的关联属性 relation(true)
        ),
    );

    //类型
    Public  $lx=array('','单程','往返','联程','缺口','本地始发','异地到异地','异地到本地');
    //状态
    Public  $status=array('申请中','已订座','已调度','已出票','配送中','部分出票','','客户消','已取消','完成');

    //cjrlx
    Public $cjrlx=array('','成人','儿童','婴儿');

    protected $_auto = array (
        array('update_time','time',3,'function'),
    );

    //asms 检测登陆操作
    function check_login(){
        //  echo date('Y-m-d H:i:s',filemtime(COOKIE_FILE));
        if(filemtime(COOKIE_FILE)>(time()-500)) return true;
        $fields = "bh=".C('ASMS_ACCOUNT')."&method=checkLogin&kl=".C('ASMS_PWD')."&call=&callnum=&randtime"; //登陆post 数据
        $index=curl_post(C('ASMS_HOST').'/asms/','',COOKIE_FILE,0);
        if(!$index){
            return  curl_login(C('ASMS_HOST').'/sysmanage/login.shtml',$fields,COOKIE_FILE);
        }
    }


    //Order 获取订单信息
    function getOrderInfo($ddbh){
        if(empty($ddbh)) return false;
        if(is_array($ddbh)){
            $ddbh=$ddbh['ddbh'];
        }
        $orderRs=$this->find($ddbh);
        if(!C('ASMS_ONLINE')) return $orderRs; //没连asms 直接返回缓存数据
        //缓存更新
        if(isset($orderRs['info_update_time']) && $orderRs['info_update_time']>(time()-C('CACHE_TIME'))){
            return $orderRs;
        }

        $this->check_login();
        $url= C('ASMS_HOST')."/asms/ydzx/ddgl/kh_khdd_xq.shtml?ddbh=".$ddbh;
     //   echo $url;
        $data=curl_post($url,'',COOKIE_FILE,0);
        if(!$data){return -1;}
        $preg="/<div class=\"nav_junior_con\".*?>(.*?)<script.*?<tr id=\"hctr0\">(.*?)<\/table>.*?<tbody id=\"tb\">(.*?)<\/tbody>/si";

        preg_match($preg,$data,$info);

        if(empty($info[0])){
            $this->error="GETORDERINFO 未找到";
            return false;
        }
        $data1=$info[1];
        $data2=$info[2];
        $data3=$info[3];//从html页面上取需要的
    //    print_r($data1);

        $preg1="/<input .*?name=\"(.*?)\" .*?value=\"(.*?)\".*?>/";
        preg_match_all($preg1,$data1,$info1);

        foreach($info1 as $key=>$val){
            foreach($val as $k=>$v){
                if($key==0) continue;
                if($key==1)
                    $kk[$k]=$v;
                if($key==2)
                    $arr[$kk[$k]]=$v;
            }
        }
        $arr['hyid']=$arr['ct_hyid'];
        $preg="/<td.*?>(.*?)<\/td>.*?<td.*?>(.*?)<\/td>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<input .*?value=\"(.*?)\"\/>.*?<td.*?>(.*?)<\/td>.*?<td.*?>(.*?)<\/td>.*?<\/tr>/si";

        preg_match_all($preg,$data2,$info2);
      //  print_R($info2);
        $preg2="/<input .*?name=\"(.*?)\" .*?value=\"(.*?)\".*?>/";
        preg_match_all($preg2,$data3,$info3);
        $hdArr=array('','hbh','hc','hd_id','hd_hbh','hd_cfcity','hd_ddcity','hd_cfsj','hd_cfsj_p','hd_bzbz','hd_bzbz_p','hd_cw','hd_fjjx','hd_hzl','hd_cityname','hd_ddcityname','cfsj','ddsj');
        foreach($info2 as $key=>$val){
            foreach($val as $k=>$v){
                if($key==0) continue;
                $arr1[$k][$hdArr[$key]]=$v;
            }
        }

     //   print_r($arr1);
     //   exit;
        $index=0;
        foreach($info3 as $key=>$val){
            foreach($val as $k=>$v){
                if($key==0) continue;
                if($v=='cjr_index'){
                    $index=$info3[2][$k];
                }
                if($key==1){
                   $kk[$k]=$v;
                   $arr2[$index][$v]=$info3[2][$k];
                }
            }
        }
        unset($data);
        $data=$arr;
        $data['ddbh']=$ddbh;
        $data['info_update_time']=time();
        $data['hd_info']=json_encode($arr1);
        $data['cjr_info']=json_encode($arr2);
     //   $rs=$this->find($ddbh);
        if($orderRs){ //存在则保存
            $save=$this->create($data);
            $this->save($save);
        }else{
            $this->create($data);
            $this->add();
        }
    //    echo ($this->getDbError());
        $arr['hd']=$arr1;
        $arr['cjr']=$arr2;
        $rs=$this->find($ddbh);
        $rs['hd_info']=$arr1;
        $rs['cjr_info']=$arr2;
        return $rs;
    }

    /*
     * 订单查找
     * data
     * cjr  乘机人
     * nxdh 联系电话
     * pnr  P N R
     * userid 订票员
     * shc  航  程
     * tkno 票号
     * hbh 航班号
     * ddzt 订单状态
     * zf_fkf 付款状态
     * zkfx 客户类型
     *
     */
    function orderFindAll($data,$is_info=0){
        if(is_array($data)){
            $arr_post=$data;
        }else{
            $this->error='第一参数只能是数组';
            return false;
        }
        $hyid=$data['khid'];
        $page_r=isset($_GET['page_r'])?$_GET['page_r']:100;
        $page_p=isset($_GET['page_p'])?$_GET['page_p']:1;
        $start=$page_r*$page_p-$page_r;
        $page_start=$start;

        $arr_post['ksrq']=isset($data['ksrq'])?$data['ksrq']:"2013-09-01"; //开始日期
        $arr_post['jsrq']=isset($data['jsrq'])?$data['jsrq']:date("Y-m-d",time()); //结束日期
        $arr_post['old_ssddlx']=1;
        $arr_post['ssddlx']=1;
        $arr_post['checkdate']=2; //预定日期
        $arr_post['pnr_hcglgj']=isset($data['pnr_hcglgj'])?$data['pnr_hcglgj']:0; //国际
        $this->check_login();
        $url=C('ASMS_HOST')."/asms/ydzx/ddgl/kh_khdd_ddgl.shtml?cs=5&count=$page_r&start=$page_start&";

        $str= http_build_query($arr_post);
     //   echo $url.$str;
        $data=curl_post($url,$str,COOKIE_FILE);
        if(!$data){return -1;}
        if(empty($data)){
            $this->error="连接失败";
            return false;
        }

        $data_preg="/<form name=\"batchForm\" .*?>(.*?)<\/form>/s";

        preg_match($data_preg,$data,$data2);
        //print_r($data[1]);
        //正则匹配数据
        //                                                                                      1  确定出票时间                   2订票员                     预订时间 3               4订单状态              5订单类型          6退改           7 采购状态       8   供应状态                 9 当前营业部                            10   订票营业部                                11  pnr.                              12    PNR 状态  .                     13 大编码          14 客户类型           15 会员卡号 /单位编号       16 客户名称         17 类型          18 航程          19航班号.           20舱 位           21起飞时间          22乘机人                 23人数              24采购价            25  账单价           26留款            27 加价 /让利           28销售价           29机建                30税费            31小计            32保险                            33接车                                34其他                35应收金额               36支付          37已付金额            38支付科目            39 OFFICE            40电子邮件         41可用积分        42旅客联系人        43联系电话             44配送方式 .      45配送时间             46订票公司          47 新PNR             48客户订单号     49 订单编号         50 订单来源
        $preg="/<tr class=.*?>.*?<td>.*?<\/td>\s<td>.*?<\/td>\s<td>.*?delRecord\(\'\d+\',\'\d+\',\'(\d+)\'.*?\).*?<\/td>\s<td>.*?<\/td>\s<td>(.*?)<\/td>\s<td>.*?<span .*?>(.*?)<\/span>.*?<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>.*?<span .*?>(.*?)<\/span>.*?<\/td>\s<td>.*?<span .*?>(.*?)<\/span>.*?<\/td>\s<td.*?>.*?<font .*?>(.*?)<\/font>.*?<\/td>\s<td.*?>.*?<font.*?>(.*?)<\/font>.*?<\/td>\s<td>(.*?)<\/td>\s<td><font.*?>(.*?)<\/font><\/td>\s<td>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td>(.*?)<\/td>\s<td(.*?)<\/td>\s<td>(.*?)<\/td>\s<td>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?><font .*?>(.*?)<\/font><\/td>\s<td.*?><font .*?>(.*?)<\/font><\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td(.*?)<\/td>\s<td(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>\s<td.*?>(.*?)<\/td>.*?<\/tr>/si";

        preg_match_all($preg,$data2[1],$info);
        if(empty($info[0])){
            $this->error=" memberFindAll 未找到";
            return false;
        }

      //  print_r($info);exit;

         $pregs="/<table .*?class=\"turnPlan\">.*?<tr>.*?<b>(\d+)<\/b>.*?<input .*?value=\'(\d+)\'>.*?<\/table>/si";
          preg_match($pregs,$data2[1],$infos);
        if(!empty($infos[0])){
            $_GET['page_n']=$infos[1];
            $_GET['page_p']=$infos[2];
            $_GET['page_r']=$page_r;
            $_GET['page_tr']=Ceil($infos[1]/$page_r);
        }
        //设置 key值
        $kayName=array('','version','cpsj','userid','dprq','ddzt','ddlx','gt','cgzt','gyzt','yyb','dpyyb','pnr','pnr_zt','hkgs_pnr','zkfx','hykh','xm','lx','hc','hbh','cw','qfsj','cjr','rs','cgj','zdj','lk','jjrl','xsj','jj','sf','xj','bx','jc','qt','ysje','zf_fkf','yf','zf_fkkm','office','email','kyjf','nklxr','lxdh','ps_lx','tyqsj','dpgs','xpnr','khddh','ddbh','ddly');        //  print_r($info);


        //格式化数组
        foreach($info as $key=>$val){
            foreach($val as $k=>$v){
                if($key==0) continue;
                if(in_array($key,array('19','23','43','44'))){
                    if(strstr($v,'title')){
                        preg_match("/title=\"(.*?)\"/",$v,$vf);
                        $v=$vf[1];
                    }else{
                        $v=strip_tags($v);
                        $v=str_replace('>','',$v);
                    }
                }
                if($key==7){
                    $v='';
                }
                if($key==2 || $key==5 || $key==7){
                    $v=strip_tags($v); //去html
                }
                if($key==5){ //订单状态
                   foreach($this->status as $kk=>$kv){
                       if($v==$kv){ $v=$kk;break;}
                   }
                }
                if($key==18){//类型
                    foreach($this->lx as $kk=>$vv){
                        if($vv==$v){
                            $v=$kk;
                            break;
                        }
                    }
                }
                if($key==37){
                    $v=$v=='未付'?0:1; //付款类型
                }
                $arr[$k][$kayName[$key]]=$v;
            }
        }

     //   print_R($kayName);
     //   print_r($arr);
        //更新保存到数据库
        foreach($arr as $key=>$val){
            if($is_info){ //详细
                $info=$this->getOrderInfo($val['ddbh']);
                $arr[$key]['hd_info']=$info['hd_info'];
                $arr[$key]['cjr_info']=$info['cjr_info'];
            }else{
                $rs=$this->find($val['ddbh']);
                $val['hyid']=$hyid;
                if($rs){
                    $save= $this->create($val);
                    $this->save($save);
                }else{
                    $this->create($val);
                    $this->add();
                }
            }
        }
        return $arr;
    }


    /**
     * 查找会员订单
     * @param $hyid
     * @param string $hykh
     * @param int $update
     * @return bool|int
     */
    function orderFind($hyid,$update=0){
        $where=array();
        if(is_array($hyid)){
            $where=$hyid;
        //    $hyid=$hyid['hyid'];
        }else{
            $hyid && $where['hyid']=$hyid;
        }

        if(empty($where)){
            $this->error="参数输入有误";
            return false;
        };
        $dbRs=$this->where($where)->select();
        if(!C('ASMS_ONLINE')) return $dbRs; //没连asms 直接返回缓存数据
        if($dbRs){
            if(!$update){
                if($dbRs[0]['update_time']>(time()-C('CACHE_TIME'))){// 缓存更新
                    return $dbRs;
                }
            }
        }
        $where='';
        if(is_array($hyid)){
            $where['khid']=$hyid['hyid'];
            $where['khmc']=$hyid['hykh'];
            $where['pnr']=$hyid['pnr'];
            $where['tkno']=$hyid['tkno'];
        }else{
            $hyid && $where['khid']=$hyid;
        }
        if(empty($where))  return false;
        $rs= $this->orderFindAll($where);
        if($rs){
            return $rs;
        }else{
            return false;
        }
    }


    //取消订单
    function orderCancel($ddbh){
        if(!$ddbh) return false;
        $this->check_login();
        $url= C('ASMS_HOST')."/asms/ydzx/ddgl/kh_khdd_cancle.shtml";
        $post['p']='cancleDd';
        $post['ddbh']=$ddbh;
        $post['qxyybh']='';
        $post['qxyynr']='';
        $post['sffc']='';
        $rs=$this->find($ddbh);
        $post['version']=$rs['version'];
        $post= http_build_query($post);
        $data=curl_post($url,$post,COOKIE_FILE);

        if(!$data)  return true;
        $this->error=$data;
        return false;
    }

    //取消全部订单
    function orderCancelAll($ddbh){
        if(!$ddbh) return false;
        if(is_array($ddbh)){
            foreach($ddbh as $val){
                $rs=$this->orderCancel($val);
                if(!$rs) return $rs;
            }
        }else{
            $rs= $this->orderCancel($ddbh);
        }
        return $rs;
    }

    /*
     * 订单支付
     * $ddbh  订单编号
     * $hyid  会员id
     * $zf_je  支付金额
     * $pay_id 支付流水
     * $bzbz 备注
     */
    function orderPay($ddbh,$hyid,$zf_je,$pay_id,$bzbz=''){
        $this->check_login();
        $url= C('ASMS_HOST')."/asms/ydzx/ddgl/kh_khdd_pay.shtml";
        $post['p']='save';
        $post['hyid']=$hyid;
        $post['ddbh']=$ddbh;
        $post['ddbh_o']=$ddbh;
        $post['zf_je']=$zf_je;
        $post['zf_zfzh']=$pay_id;
     //   $post['fpj_hj']='0.01';
        $post['zfkm']='1006417';
      //  $post['zffs1006417']='1006309';
        $post['ywType']='1';
        $post['bzbz']=$bzbz;
        $rs=$this->find($ddbh);
        $post['version']=$rs['version'];

        $post['cj_compid']='GZML';
        $post['cj_deptid']='GZMLDZSW';
        $post['cj_userid']='6000';
        $post['cjrname']='DSFS/MS';
        $post['qbook']='';
        $post['operate']='create';
        $post['b2gzh']='';
        $post['zfgsid']='';
        $post['ipsvalue']='';
        $post['epos_zhivr']='';
        $post['hkgs']='AA';
        $post= http_build_query($post);
        $data=curl_post($url,$post,COOKIE_FILE);
        if(strpos($data,'正在处理')){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 我的订单
     * 适应于会员查看自己的订单
     */
    function myOrder(){
        if(!ASMSUID){
           $this->error="未登陆";
           return false;
        }
        return $this->orderFind(ASMSUID);
    }

    /*
     * 订单删除
     * 用于 删除本地同步出错的
     */
    function orderDel($ddbh){
        if($ddbh==''){
            $this->error="";
            return false;
        }
        return $this->delete($ddbh);
    }


    //格式化
    function format($data){
        if(!isset($data[0])){
            $data=array(0=>$data);
            $on=1;
        }
        foreach($data as $key=>$val){
            $data[$key]['ddzt_n']=$this->status[$val['ddzt']];
            $data[$key]['lx_n']=$this->lx[$val['lx']];
            $data[$key]['zf_zt']=$val['zf_fkf']==1?"已付款":'待支付';
        }

        if($on){
            return $data[0];
        }
        return $data;
    }

    //订单支列表
    function orderPayList($data){
        $where['member_id']=session('asmsUid');
        foreach($data as $k=>$v){
            $booking[$k]=$this->getOrderInfo($v);
        }

        $where['ddbh']=array("in",$data);
        $where['zf_zt']=0;
        $list=$this->where($where)->select();

        $array='';
        $this->total_price=0;
        foreach($list as $key=>$val){
            $list[$key]['hd_info']=object_to_array(json_decode($val['hd_info']));
            $list[$key]['cjr_info']=object_to_array(json_decode($val['cjr_info']));
            foreach( $list[$key]['cjr_info'] as $k=>$v){
                $list[$key]['cjr_info'][$k]['lx']=$this->cjrlx[$v['cjr_cjrlx']];
            }
            $array['total_price']+=(float)$val['xj'];
        }
        $array['list']=$list;
        return $array;
    }
		
}

?>