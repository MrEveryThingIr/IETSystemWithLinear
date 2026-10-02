<?php

return [
    'title' => '商品/服务条目',
    'editor_title' => '条目编辑器',
    'editor_help' => '按简单顺序完善商业记录，预览客户实际可见的内容，并在准备好后发布。',
    'back_catalog' => '返回目录',
    'save' => '保存草稿',
    'preview' => '客户预览',
    'publish' => '发布此版本',
    'published' => '已发布版本',
    'advanced_content' => '打开高级展示编辑器',
    'sync_content' => '刷新客户展示',
    'version' => '版本 :version',
    'history_notice' => '如果当前版本已发布，保存会创建新的工作版本，不会修改已发布历史。',
    'steps' => ['client' => '1. 客户', 'purpose' => '2. 目的与位置', 'property' => '3. 房产', 'facilities' => '4. 建筑与设施', 'price' => '5. 价格', 'media' => '6. 媒体', 'preview' => '7. 预览', 'publish' => '8. 发布'],
    'client' => ['title' => '客户 / 业主', 'help' => '需要时关联真实客户；客户身份和私人联系方式不会进入公开展示。', 'none' => '未关联客户'],
    'basics' => ['title' => '用途与公开信息', 'listing_title' => '标题', 'short_description' => '简短介绍', 'description' => '描述', 'category' => '分类', 'visibility' => '可见性', 'availability' => '可用状态', 'available_from' => '可用起始', 'available_until' => '可用结束', 'simple_mode' => '简洁办公模式', 'simple_mode_help' => '保持日常表单简短，高级建筑字段按需展开。'],
    'property' => ['title' => '房产与位置', 'transaction_mode' => '交易方式', 'sale' => '出售', 'rent' => '出租', 'sale_or_rent' => '出售或出租', 'property_class' => '房产类别', 'property_subtype' => '子类型', 'public_area' => '公开区域/社区', 'exact_address' => '精确地址—私密', 'privacy_help' => '精确地址、关联客户与私密备注只供内部使用。', 'land_area' => '土地面积', 'construction_area' => '建筑面积', 'width' => '宽度', 'length' => '长度', 'frontage_count' => '临街面数', 'built_year' => '建成年份', 'built_year_calendar' => '历法', 'building_age_years' => '楼龄', 'building_condition' => '状态', 'bedrooms' => '卧室', 'floor_number' => '楼层', 'total_floors' => '总楼层', 'unit_number' => '单元号', 'units_per_floor' => '每层单元数', 'orientation' => '朝向', 'deed_type' => '产权类型', 'usage_type' => '用途', 'occupancy_status' => '占用状态', 'latitude' => '纬度', 'longitude' => '经度', 'public_notes' => '公开备注', 'private_notes' => '内部备注'],
    'facilities' => ['title' => '建筑与设施', 'advanced' => '更多建筑详情', 'cabinet_type' => '橱柜', 'false_ceiling' => '吊顶', 'heating' => '供暖', 'cooling' => '制冷', 'yard' => '院落', 'flooring' => '地面', 'parking' => '停车', 'parking_type' => '停车类型', 'parking_spaces' => '车位数', 'car_capacity' => '汽车容量', 'motorbike_capacity' => '摩托容量', 'roof_finish' => '屋面', 'roof_parapet' => '屋顶护栏', 'western_toilet' => '西式卫生间', 'iranian_toilet' => '伊朗式卫生间', 'elevator' => '电梯', 'storage' => '储藏室', 'storage_area' => '储藏面积', 'balcony' => '阳台', 'balcony_area' => '阳台面积', 'utilities' => '公用设施（逗号分隔）', 'facilities' => '其他设施', 'unknown' => '未知', 'yes' => '有', 'no' => '无'],
    'price' => ['title' => '价格', 'help' => '每次变更创建追加式价格版本，也接受波斯/阿拉伯数字。', 'type' => '价格类型', 'unit' => '货币单位', 'amount' => '金额', 'basis' => '计价基础', 'visibility' => '价格可见性', 'reason' => '变更说明', 'add' => '添加价格版本'],
    'media' => ['title' => '照片、视频和音频', 'help' => '媒体复用 Business Context Asset；只有公开媒体进入客户展示。', 'file' => '媒体文件', 'rights' => '权利状态', 'caption' => '说明', 'visibility' => '可见性', 'cover' => '设为封面', 'position' => '顺序', 'upload' => '添加媒体', 'update' => '更新媒体', 'remove' => '移除', 'empty' => '尚未添加媒体。'],
    'preview_page' => ['label' => '客户预览', 'private_notice' => '此预览不会显示客户身份、精确地址或内部备注。', 'details' => '房产详情', 'price' => '价格', 'media' => '媒体'],
    'availability' => ['available' => '可用', 'reserved' => '已预订', 'under_contract' => '合同处理中', 'unavailable' => '不可用', 'sold' => '已售', 'rented' => '已租', 'withdrawn' => '已撤回'],
    'messages' => [
        'market_published' => '已发布的目录版本现已成为正式市场 Offer。接下来查看匹配项。',
        'client_need_published' => '客户 Need 已发布到正式市场。接下来查看匹配 Offer。','created' => '已创建草稿。', 'saved' => '已保存草稿。', 'media_added' => '已添加媒体。', 'media_updated' => '已更新媒体。', 'media_removed' => '已从草稿移除媒体。', 'presentation_synced' => '已刷新展示 Content。', 'published' => '条目版本及其展示已发布并冻结。'],
];
