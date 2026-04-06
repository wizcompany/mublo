/**
 * BE.Adapter — 서버 JSON ↔ 에디터 상태 변환
 */
window.BE = window.BE || {};

BE.Adapter = {
    fromServer(data) {
        const row = { ...data.row };
        if (typeof row.background_config === 'string') {
            row.background_config = JSON.parse(row.background_config || '{}');
        }

        const columns = (data.columns || []).map(col => {
            const c = { ...col };
            ['background_config', 'border_config', 'title_config', 'content_config', 'content_items']
                .forEach(key => {
                    if (typeof c[key] === 'string') {
                        try { c[key] = JSON.parse(c[key] || '{}'); } catch { c[key] = {}; }
                    }
                    if (!c[key]) c[key] = key === 'content_items' ? [] : {};
                });
            return c;
        });

        return { row, columns };
    },

    /**
     * Store 상태 → 기존 store()/preview() 형식 페이로드
     * processBackgroundConfig() 호환: background_config를 개별 필드로 분해
     */
    toFormData() {
        const Store = BE.Store;
        const row = Store.getRow();
        const bg = row.background_config || {};

        const formData = {
            row_id: row.row_id || 0,
            page_id: row.page_id || 0,
            position: row.position || '',
            position_menu: row.position_menu || '',
            admin_title: row.admin_title || '',
            section_id: row.section_id || '',
            is_active: row.is_active ? 1 : 0,
            width_type: parseInt(row.width_type) || 0,
            column_count: parseInt(row.column_count) || 1,
            column_margin: parseInt(row.column_margin) || 0,
            column_width_unit: parseInt(row.column_width_unit) || 1,
            pc_height: row.pc_height || '',
            mobile_height: row.mobile_height || '',
            pc_padding: row.pc_padding || '',
            mobile_padding: row.mobile_padding || '',
            sort_order: parseInt(row.sort_order) || 0,
            bg_color: bg.color || '',
            bg_gradient: bg.gradient || '',
            bg_image_old: bg.image || '',
            bg_size: bg.size || 'cover',
            bg_position: bg.position || 'center center',
            bg_repeat: bg.repeat || 'no-repeat',
            bg_attachment: bg.attachment || 'scroll',
        };

        const columns = Store.getColumns().map((col, i) => ({
            column_index: i,
            width: col.width || '',
            pc_padding: col.pc_padding || '',
            mobile_padding: col.mobile_padding || '',
            content_type: col.content_type || '',
            content_kind: col.content_kind || 'CORE',
            content_skin: col.content_skin || '',
            background_config: col.background_config || {},
            border_config: col.border_config || {},
            title_config: col.title_config || {},
            content_config: col.content_config || {},
            content_items: col.content_items || [],
            is_active: col.is_active ? 1 : 0,
        }));

        return { formData, columns };
    }
};
