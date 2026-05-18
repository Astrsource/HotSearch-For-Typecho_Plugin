# HotSearch - Typecho 热门搜索插件

---

## 一、插件简介

HotSearch 是一款 Typecho 热门搜索插件，自动记录访客搜索的关键词，按热度排序，支持通过后台模板占位符完全自定义前端展示样式。

### 核心特性

- **自动记录**：访客使用 Typecho 原生搜索时自动记录关键词与次数
- **模板占位符**：后台支持自定义 HTML 模板，通过占位符注入数据
- **数据安全**：默认禁用插件时保留数据表，可选开启删除
- **兼容伪静态**：自动适配普通模式与伪静态模式的搜索链接

---

## 二、安装步骤

### 1. 上传插件

在 Typecho 安装目录下找到 `usr/plugins/`，新建文件夹：

```
usr/
└── plugins/
    └── HotSearch/
        └── Plugin.php
```

将插件代码保存为 `Plugin.php` 放入该文件夹。

### 2. 启用插件

登录 Typecho 后台：

> **控制台** → **插件** → 找到「热门搜索」→ 点击 **启用**

首次启用会自动创建数据表 `{prefix}_search_log`。

### 3. 配置插件

点击插件名称进入 **设置页面**，根据需求调整参数。

---

## 三、后台配置详解

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| **前台显示数量** | 数字 | `10` | 前台展示多少个热门搜索词 |
| **自动记录搜索** | 单选 | `开启` | 关闭后不再自动记录，需手动调用 `HotSearch_Plugin::log()` |
| **禁用插件时删除数据表** | 单选 | `否` | 开启后禁用插件将 **永久删除** 所有搜索记录 |
| **外层容器模板** | 文本域 | `<ul class="hot-search-list">{items}</ul>` | 列表外层 HTML，使用 `{items}` 注入渲染后的列表项 |
| **单项模板** | 文本域 | `<li><a href="{url}" title="{keyword}">{keyword}<span class="hot-search-count">({count})</span></a></li>` | 单个搜索词的 HTML 结构 |
| **空数据模板** | 文本域 | `<p class="hot-search-empty">暂无热门搜索</p>` | 没有任何记录时的提示内容 |

---

## 四、模板占位符列表

### 外层容器模板

| 占位符 | 说明 |
|--------|------|
| `{items}` | 将被替换为所有渲染后的单项 HTML |

### 单项模板

| 占位符 | 说明 | 示例输出 |
|--------|------|----------|
| `{keyword}` | 搜索关键词（已转义） | `Typecho` |
| `{count}` | 被搜索的次数 | `42` |
| `{url}` | 该关键词的搜索链接 | `https://example.com/search/Typecho/` |
| `{index}` | 当前序号（从 1 开始） | `1` |

### 空数据模板

无占位符，直接填写纯静态 HTML 即可。若留空，无记录时不输出任何内容。

---

## 五、主题调用方法

### 方法 A：直接渲染（推荐）

将以下代码放入主题模板（如 `sidebar.php`、`footer.php`）：

```php
<?php if (class_exists('HotSearch_Plugin')): ?>
<div class="widget hot-search-widget">
    <h3 class="widget-title">热门搜索</h3>
    <?php HotSearch_Plugin::render(); ?>
</div>
<?php endif; ?>
```

### 方法 B：获取数组自行处理

如需完全自定义 PHP 逻辑：

```php
<?php
if (class_exists('HotSearch_Plugin')) {
    $list = HotSearch_Plugin::getHotSearch(10);
    if (!empty($list)) {
        foreach ($list as $index => $item) {
            $url = Typecho_Router::url(
                'search',
                array('keywords' => urlencode($item['keyword'])),
                Helper::options()->index
            );
            echo '<a href="' . $url . '">' . htmlspecialchars($item['keyword']) . '</a>';
        }
    }
}
?>
```

---

## 六、模板风格示例

### 示例 1：默认列表风格（自带样式）

**外层容器模板：**
```html
<ul class="hot-search-list">{items}</ul>
```

**单项模板：**
```html
<li>
  <a href="{url}" title="{keyword}">
    {keyword}
    <span class="hot-search-count">({count})</span>
  </a>
</li>
```

---

### 示例 2：排行列表（带序号）

**外层容器模板：**
```html
<ol class="hot-search-rank">{items}</ol>
```

**单项模板：**
```html
<li>
  <span class="rank-num">{index}</span>
  <a href="{url}">{keyword}</a>
  <span class="hot-count">{count} 次</span>
</li>
```

---

### 示例 3：标签云风格（无次数）

**外层容器模板：**
```html
<div class="hot-search-cloud">{items}</div>
```

**单项模板：**
```html
<a href="{url}" class="hot-tag">{keyword}</a>
```

---

### 示例 4：卡片网格风格

**外层容器模板：**
```html
<div class="hot-search-grid">{items}</div>
```

**单项模板：**
```html
<div class="hot-card">
  <a href="{url}">
    <strong>{keyword}</strong>
    <small>热度 {count}</small>
  </a>
</div>
```

---

## 七、手动记录备用方案

如果你的主题使用了 **AJAX 搜索** 或其他非标准搜索方式，导致自动记录失效，可在搜索处理处手动调用：

```php
HotSearch_Plugin::log($keyword);
```

或在传统搜索模板 `search.php` 顶部加一道保险：

```php
<?php
if (class_exists('HotSearch_Plugin') && !empty($_GET['s'])) {
    HotSearch_Plugin::log($_GET['s']);
}
?>
```

---

## 八、常见问题

### Q1：搜索后没有记录？

1. 确认插件已启用且「自动记录搜索」为开启状态
2. 确认使用的是 Typecho 原生搜索（非前端 AJAX）
3. 尝试禁用后重新启用插件
4. 检查数据库中 `{prefix}_search_log` 表是否存在

### Q2：数据表在哪里？

表名为 `search_log`，前缀与你的 Typecho 数据库前缀一致。例如前缀为 `typecho_`，则表名为 `typecho_search_log`。

### Q3：禁用插件后数据还在吗？

默认 **保留**。如需删除，在插件设置中开启「禁用插件时删除数据表」后再禁用。

### Q4：如何清空历史记录？

进入数据库管理工具（如 phpMyAdmin），执行：

```sql
TRUNCATE TABLE `typecho_search_log`;
```

或删除该表后重新启用插件。

### Q5：搜索次数有上限吗？

`count` 字段类型为 `int(10) unsigned`，上限约 **42 亿次**，正常使用无需担心。

---

## 九、注意事项

- **性能**：数据量小时无需优化；若搜索量极大，建议给 `count` + `lastsearch` 加联合索引，或定期归档旧数据。
- **缓存**：热门搜索列表目前实时查询，若访问量高，建议在主题层面做文件缓存。
- **安全**：插件已对所有关键词输出做 `htmlspecialchars()` 转义，防止 XSS。
