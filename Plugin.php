<?php
/**
 * Typecho 热门搜索插件
 * 插件用于在 Typecho 站点前台展示热门搜索词。
 * @package HotSearch
 * @author Astrsource
 * @version 1.0.0
 * @link https://github.com/Astrsource/HotSearch-For-Typecho_Plugin/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class HotSearch_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件：创建数据表并挂载到 header 钩子
     */
    public static function activate()
    {
        self::initTable();
        
        Typecho_Plugin::factory('Widget_Archive')->header = array('HotSearch_Plugin', 'track');
        
        return _t('热门搜索插件已激活，数据表已创建。请进入插件设置调整显示数量与模板。');
    }

    /**
     * 禁用插件：根据设置决定是否删除数据表
     */
    public static function deactivate()
    {
        $cfg = Helper::options()->plugin('HotSearch');
        
        if ($cfg && $cfg->deleteOnDeactivate == '1') {
            $db = Typecho_Db::get();
            $table = $db->getPrefix() . 'search_log';
            
            try {
                $db->query("DROP TABLE IF EXISTS `" . $table . "`");
                return _t('热门搜索插件已禁用，数据表已删除。');
            } catch (Exception $e) {
                return _t('热门搜索插件已禁用，但数据表删除失败：') . $e->getMessage();
            }
        }
        
        return _t('热门搜索插件已禁用，数据表未删除。');
    }

    /**
     * 插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $limit = new Typecho_Widget_Helper_Form_Element_Text(
            'limit',
            NULL,
            '10',
            _t('前台显示数量'),
            _t('默认展示多少个热门搜索词')
        );
        $limit->input->setAttribute('class', 'mini');
        $form->addInput($limit->addRule('isInteger', _t('请填写数字')));

        $autoTrack = new Typecho_Widget_Helper_Form_Element_Radio(
            'autoTrack',
            array('1' => _t('开启'), '0' => _t('关闭')),
            '1',
            _t('自动记录搜索'),
            _t('开启后插件会自动记录搜索关键词；若你的主题使用了自定义搜索逻辑导致无法自动记录，可关闭此项并在搜索模板中手动调用 HotSearch_Plugin::log($keyword)')
        );
        $form->addInput($autoTrack);

        $deleteOnDeactivate = new Typecho_Widget_Helper_Form_Element_Radio(
            'deleteOnDeactivate',
            array('1' => _t('是'), '0' => _t('否')),
            '0',
            _t('禁用插件时删除数据表'),
            _t('开启后，禁用插件时会自动删除搜索记录数据表。<br><strong style="color:#c00;">警告：开启后禁用插件将永久删除所有搜索记录数据，无法恢复！</strong>')
        );
        $form->addInput($deleteOnDeactivate);

        // 外层容器模板
        $wrapperTemplate = new Typecho_Widget_Helper_Form_Element_Textarea(
            'wrapperTemplate',
            NULL,
            '<ul class="hot-search-list">{items}</ul>',
            _t('外层容器模板'),
            _t('可用占位符：<code>{items}</code> — 将被替换为渲染后的列表项内容。留空则默认使用 &lt;ul&gt; 包裹。')
        );
        $form->addInput($wrapperTemplate);

        // 单项模板
        $itemTemplate = new Typecho_Widget_Helper_Form_Element_Textarea(
            'itemTemplate',
            NULL,
            '<li><a href="{url}" title="{keyword}">{keyword}<span class="hot-search-count">({count})</span></a></li>',
            _t('单项模板'),
            _t('可用占位符：<br><code>{keyword}</code> — 搜索词<br><code>{count}</code> — 搜索次数<br><code>{url}</code> — 搜索链接<br><code>{index}</code> — 当前序号（从1开始）')
        );
        $form->addInput($itemTemplate);

        // 空数据模板
        $emptyTemplate = new Typecho_Widget_Helper_Form_Element_Textarea(
            'emptyTemplate',
            NULL,
            '<p class="hot-search-empty">暂无热门搜索</p>',
            _t('空数据模板'),
            _t('当没有任何搜索记录时显示的 HTML。可用占位符：无。留空则不输出任何内容。')
        );
        $form->addInput($emptyTemplate);
    }

    /**
     * 个人用户配置（无需使用）
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * 初始化数据库表
     */
    private static function initTable()
    {
        $db = Typecho_Db::get();
        $table = $db->getPrefix() . 'search_log';

        $sql = "CREATE TABLE IF NOT EXISTS `" . $table . "` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `keyword` varchar(255) NOT NULL DEFAULT '',
            `count` int(10) unsigned NOT NULL DEFAULT '1',
            `lastsearch` int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            UNIQUE KEY `keyword` (`keyword`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $db->query($sql);
    }

    /**
     * 搜索行为追踪（挂载于 Widget_Archive->header）
     */
    public static function track($header = '', $archive = null)
    {
        static $tracked = false;
        if ($tracked) {
            return $header;
        }

        $cfg = Helper::options()->plugin('HotSearch');
        if ($cfg && $cfg->autoTrack == '0') {
            return $header;
        }

        $isSearch = false;
        $keyword = '';

        if ($archive && method_exists($archive, 'is') && $archive->is('search')) {
            $isSearch = true;
            $request = Typecho_Request::getInstance();
            $keyword = $request->get('s', '');
            
            if (empty($keyword)) {
                $pathInfo = trim($request->getPathInfo(), '/');
                if (strpos($pathInfo, 'search/') === 0) {
                    $keyword = urldecode(substr($pathInfo, 7));
                }
            }
        } else {
            $request = Typecho_Request::getInstance();
            $keyword = $request->get('s', '');
            $pathInfo = trim($request->getPathInfo(), '/');
            
            if (!empty($keyword)) {
                $isSearch = true;
            } else if (strpos($pathInfo, 'search/') === 0) {
                $isSearch = true;
                $keyword = urldecode(substr($pathInfo, 7));
            }
        }

        if ($isSearch && !empty($keyword)) {
            self::log($keyword);
            $tracked = true;
        }

        return $header;
    }

    /**
     * 公共方法：手动记录搜索词
     */
    public static function log($keyword)
    {
        $keyword = trim($keyword);
        if (empty($keyword) || strlen($keyword) > 255) {
            return;
        }

        $keyword = str_replace(array('\\', "\0", "'", '"', '<', '>'), '', $keyword);

        $db = Typecho_Db::get();
        $table = $db->getPrefix() . 'search_log';

        $row = $db->fetchRow(
            $db->select()->from($table)->where('keyword = ?', $keyword)
        );

        if ($row) {
            $db->query(
                $db->update($table)->rows(array(
                    'count' => $row['count'] + 1,
                    'lastsearch' => time()
                ))->where('id = ?', $row['id'])
            );
        } else {
            $db->query(
                $db->insert($table)->rows(array(
                    'keyword' => $keyword,
                    'count' => 1,
                    'lastsearch' => time()
                ))
            );
        }
    }

    /**
     * 获取热门搜索列表
     */
    public static function getHotSearch($limit = null)
    {
        if (is_null($limit)) {
            $cfg = Helper::options()->plugin('HotSearch');
            $limit = ($cfg && $cfg->limit) ? intval($cfg->limit) : 10;
        }

        $db = Typecho_Db::get();
        $table = $db->getPrefix() . 'search_log';

        return $db->fetchAll(
            $db->select()->from($table)
                ->order('count', Typecho_Db::SORT_DESC)
                ->order('lastsearch', Typecho_Db::SORT_DESC)
                ->limit($limit)
        );
    }

    /**
     * 直接输出热门搜索 HTML（支持后台模板占位符）
     */
    public static function render($limit = null)
    {
        $records = self::getHotSearch($limit);
        $cfg = Helper::options()->plugin('HotSearch');

        // 获取模板配置，若留空则使用默认值
        $wrapperTemplate = ($cfg && trim($cfg->wrapperTemplate)) ? $cfg->wrapperTemplate : '<ul class="hot-search-list">{items}</ul>';
        $itemTemplate = ($cfg && trim($cfg->itemTemplate)) ? $cfg->itemTemplate : '<li><a href="{url}" title="{keyword}">{keyword}<span class="hot-search-count">({count})</span></a></li>';
        $emptyTemplate = ($cfg && trim($cfg->emptyTemplate)) ? $cfg->emptyTemplate : '<p class="hot-search-empty">暂无热门搜索</p>';

        // 空数据输出
        if (empty($records)) {
            if (!empty($emptyTemplate)) {
                echo $emptyTemplate;
            }
            return;
        }

        // 渲染单项
        $itemsHtml = '';
        $index = 0;
        foreach ($records as $item) {
            $index++;
            $url = Typecho_Router::url(
                'search',
                array('keywords' => urlencode($item['keyword'])),
                Helper::options()->index
            );

            $replacements = array(
                '{keyword}' => htmlspecialchars($item['keyword']),
                '{count}'   => intval($item['count']),
                '{url}'     => $url,
                '{index}'   => $index
            );

            $itemsHtml .= strtr($itemTemplate, $replacements);
        }

        // 渲染外层容器
        echo strtr($wrapperTemplate, array('{items}' => $itemsHtml));
    }
}