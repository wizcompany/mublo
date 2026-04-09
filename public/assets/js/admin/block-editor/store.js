/**
 * BE.Store — 상태 관리 + 이벤트 시스템
 *
 * 이벤트: loaded, row-updated, column-updated, selection-changed, columns-changed, dirty
 */
window.BE = window.BE || {};

BE.Store = {
    state: {
        row: {},
        columns: [],
        selection: { type: 'row', columnIndex: null },
        dirty: false,
    },
    _listeners: {},

    on(event, fn) {
        (this._listeners[event] = this._listeners[event] || []).push(fn);
    },

    emit(event, data) {
        (this._listeners[event] || []).forEach(fn => fn(data));
    },

    load(data) {
        this.state.row = data.row || {};
        this.state.columns = data.columns || [];
        this.state.dirty = false;
        this.state.selection = { type: 'row', columnIndex: null };
        this.emit('loaded');
    },

    getRow() { return this.state.row; },
    getColumn(i) { return this.state.columns[i]; },
    getColumns() { return this.state.columns; },
    getSelection() { return this.state.selection; },
    isDirty() { return this.state.dirty; },

    updateRow(partial) {
        Object.assign(this.state.row, partial);
        this.state.dirty = true;
        this.emit('row-updated');
        this.emit('dirty');
    },

    updateColumn(index, partial) {
        if (!this.state.columns[index]) return;
        Object.assign(this.state.columns[index], partial);
        this.state.dirty = true;
        this.emit('column-updated', index);
        this.emit('dirty');
    },

    select(type, columnIndex) {
        const prev = this.state.selection;
        if (prev.type === type && prev.columnIndex === columnIndex) return;
        this.state.selection = { type, columnIndex: columnIndex ?? null };
        this.emit('selection-changed');
    },

    setColumnCount(count) {
        count = Math.max(1, Math.min(4, count));
        const current = this.state.columns.length;
        if (count === current) return;

        // 칸 수 변경 시 전체 초기화 (칸 크기에 따라 내용이 달라지므로)
        const newColumns = [];
        for (let i = 0; i < count; i++) {
            newColumns.push({
                column_id: 0, column_index: i, width: null,
                pc_padding: null, mobile_padding: null,
                content_type: '', content_kind: 'CORE', content_skin: '',
                background_config: {}, border_config: {}, title_config: {},
                content_config: {}, content_items: [], is_active: 1,
            });
        }
        this.state.columns = newColumns;

        this.state.row.column_count = count;
        this.state.dirty = true;
        this.state.selection = { type: 'row', columnIndex: null };

        this.emit('columns-changed');
        this.emit('dirty');
    },

    moveColumn(fromIndex, toIndex) {
        const cols = this.state.columns;
        if (fromIndex < 0 || fromIndex >= cols.length || toIndex < 0 || toIndex >= cols.length) return;
        const [moved] = cols.splice(fromIndex, 1);
        cols.splice(toIndex, 0, moved);
        cols.forEach((c, i) => c.column_index = i);
        this.state.dirty = true;

        if (this.state.selection.type === 'column' && this.state.selection.columnIndex === fromIndex) {
            this.state.selection.columnIndex = toIndex;
        }

        this.emit('columns-changed');
        this.emit('dirty');
    },

    markClean() {
        this.state.dirty = false;
        this.emit('dirty');
    }
};
