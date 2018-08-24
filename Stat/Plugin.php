<?php
/**
 * 页面浏览次数统计插件
 * 
 * @package Stat
 * @author Hanny
 * @version 1.0.2
 * @dependence 10.8.15-*
 * @link http://www.imhan.com
 *
 * 历史版本
 * version 1.0.3 at 2018-08-24
 * 修复PDO下数据表检测失败的错误
 *
 * version 1.0.2 at 2010-07-03
 * 终于支持前台调用了
 * 接口支持Typecho 0.8的计数
 * 增加SQLite的支持
 *
 * version 1.0.1 at 2010-01-02
 * 修改安装出错处理
 * 修改安装时默认值错误
 *
 * version 1.0.0 at 2009-12-12
 * 实现浏览次数统计的基本功能
 *
 */
class Stat_Plugin implements Typecho_Plugin_Interface
{
	
	/**
	 * 激活插件方法,如果激活失败,直接抛出异常
	 * 
	 * @access public
	 * @return void
	 * @throws Typecho_Plugin_Exception
	 */
	public static function activate()
	{
		$info = Stat_Plugin::sqlInstall();
		Typecho_Plugin::factory('Widget_Archive')->singleHandle = array('Stat_Plugin', 'singleHandle');
		Typecho_Plugin::factory('Widget_Archive')->select = array('Stat_Plugin', 'selectHandle');
		return _t($info);
	}

	//SQL创建
	public static function sqlInstall()
	{
		$db = Typecho_Db::get();
		$type = explode('_', $db->getAdapterName());
		$type = array_pop($type);
		$prefix = $db->getPrefix();
		try {
			$select = $db->select('table.contents.views')->from('table.contents');
			$db->query($select, Typecho_Db::READ);
			return '检测到统计字段，插件启用成功';
		} catch (Typecho_Db_Exception $e) {
			$code = $e->getCode();
			if( ('Mysql' == $type && (1054 == $code || $code == '42S22')) ||
					('SQLite' == $type && ('HY000' == $code || 1 == $code))) {
				try {
					if ('Mysql' == $type) {
						$db->query("ALTER TABLE `".$prefix."contents` ADD `views` INT( 10 ) NOT NULL  DEFAULT '0' COMMENT '页面浏览次数';");
					} else if ('SQLite' == $type) {
						$db->query("ALTER TABLE `".$prefix."contents` ADD `views` INT( 10 ) NOT NULL  DEFAULT '0'");
					} else {
						throw new Typecho_Plugin_Exception('不支持的数据库类型：'.$type);
					}
					return '建立统计字段，插件启用成功';
				} catch (Typecho_Db_Exception $e) {
					$code = $e->getCode();
					if(('Mysql' == $type && 1060 == $code) ) {
						return '统计字段已经存在，插件启用成功';
					}
					throw new Typecho_Plugin_Exception('统计插件启用失败。错误号：'.$code);
				}
			}
			throw new Typecho_Plugin_Exception('数据表检测失败，统计插件启用失败。错误号：'.$code);
		}
	}

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form){}
      
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 插件实现方法
     * 
     * @access public
     * @return void
     */
    public static function viewStat($cid)
    {
		$db = Typecho_Db::get();
		$prefix  = $db->getPrefix();
		$sql = "UPDATE `".$prefix."contents` SET `views` = `views` + 1 WHERE `cid` = ".intval($cid).";";
		$db->query($sql);
    }

	public static function selectHandle($archive)
	{
		$db = Typecho_Db::get();
		$options = Typecho_Widget::widget('Widget_Options');
		return $db->select('*')->from('table.contents')->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', $options->gmtTime);
	}

    public static function singleHandle($select, $archive)
    {
		Stat_Plugin::viewStat($select->stack[0]['cid']);
    }
}
