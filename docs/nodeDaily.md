# NodeDaily

NodeSeek社区信息聚合工具

## 分词工具 (NodeDailyJieba)

### 功能介绍

NodeDaily分词工具是一个用于对NodeSeek帖子标题进行中文分词的命令行工具。它使用结巴分词库对未分词的帖子标题进行分词，并将结果以JSON格式保存到数据库的tokens字段中。

### 主要特点

- 自动过滤单字符词（长度小于2的词）
- 自动过滤停用词（常见但不重要的词）
- 支持自定义用户词典，增强分词准确性
- 支持批量处理或处理所有未分词数据
- 实时显示处理进度和耗时统计

### 命令参数

```bash
php webman nodeDaily:jieba [选项]
```

#### 可用选项

- `--limit=<数量>` 或 `-l <数量>`: 指定每次处理的帖子数量，默认为100条
- `--all` 或 `-a`: 处理所有未分词的帖子数据（忽略limit参数）

### 使用示例

1. 使用默认参数处理100条未分词数据

```bash
php webman nodeDaily:jieba
```

2.处理指定数量的未分词数据

```bash
php webman nodeDaily:jieba --limit=200
```

3.处理所有未分词数据

```bash
php webman nodeDaily:jieba --all
```

### 处理逻辑

1. 工具会查询所有is_token=0的帖子记录
2. 使用结巴分词对标题进行分词
3. 过滤掉单字符词和停用词
4. 将分词结果以JSON数组格式保存到tokens字段
5. 将is_token字段设置为1，表示已处理
6. 每处理100条数据会显示一次处理进度

### 注意事项

- 确保数据库中post表已有tokens字段用于存储分词结果
- 大量数据处理可能需要较长时间，建议使用`--limit`参数分批处理
- 如需添加自定义词典，请编辑`app/command/user_dict.txt`文件
- 如需修改停用词，请编辑`app/command/stop_words.txt`文件

### 数据结构

分词结果以JSON数组形式存储，便于后续处理和分析：

```json

["关键词1", "关键词2", "关键词3", ...]

```
