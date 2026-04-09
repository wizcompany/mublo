<?php
/**
 * Table Basic Skin
 *
 * 기본 테이블 레이아웃
 *
 * 사용 가능한 변수:
 * @var array $headerSchema - 헤더 스키마 (선택적)
 * @var array $columns - 컬럼 정의
 * @var array $rows - 데이터 행
 * @var string $wrapAttr - 테이블 속성
 * @var bool $showHeader - 헤더 출력 여부
 * @var \Mublo\Helper\List\ListRenderHelper $self - Helper 인스턴스
 */
?>
<table <?= $wrapAttr ?>>
    <?php if ($showHeader): ?>
    <thead>
        <tr>
            <?php foreach ($columns as $col): ?>
                <?php
                $thAttr = $self->buildAttr($col['_th_attr'] ?? []);
                $type = $col['type'] ?? 'text';
                ?>
                <th <?= $thAttr ?>>
                    <?php if ($type === 'checkbox'): ?>
                        <input type="checkbox" name="<?= $col['key'] ?>_all" class="form-check-input">
                    <?php else: ?>
                        <?= htmlspecialchars($col['title']) ?>
                        <?php if (!empty($col['sortable'])): ?>
                            <span class="sort-icon">⇅</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <?php endif; ?>

    <tbody>
        <?php if (empty($rows)): ?>
            <tr>
                <td colspan="<?= count($columns) ?>" style="text-align: center; padding: 20px;">
                    데이터가 없습니다.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <?php
                // 콜백 기반 또는 데이터 기반 행 속성
                $trAttr = $self->buildTrAttr($row);
                $rowAttr = $self->buildAttr($row['_row_attr'] ?? []);
                ?>
                <tr <?= $trAttr ?> <?= $rowAttr ?>>
                    <?php foreach ($columns as $col): ?>
                        <?php
                        // _cell_attr 또는 _td_attr 둘 다 지원
                        $cellAttr = $self->buildAttr($col['_cell_attr'] ?? $col['_td_attr'] ?? []);
                        ?>
                        <td <?= $cellAttr ?>>
                            <?= $self->renderCell($row, $col) ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
