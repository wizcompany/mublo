<?php
/**
 * 리뷰 작성 폼 (프론트)
 *
 * @var int $orderDetailId
 */
$orderDetailId = $orderDetailId ?? 0;
?>
<style>
.shop-review-form { max-width: 640px; margin: 0 auto; padding: 24px 16px; }
.shop-review-form__title { font-size: 1.3rem; font-weight: 700; margin-bottom: 20px; }
.shop-review-form__stars { display: flex; gap: 4px; margin-bottom: 4px; }
.shop-review-form__star { font-size: 2rem; color: #d1d5db; cursor: pointer; transition: color 0.1s; }
.shop-review-form__star.active, .shop-review-form__star:hover { color: #f59e0b; }
.shop-review-form__images { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.shop-review-form__img-slot { width: 80px; height: 80px; border: 2px dashed #d1d5db; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; overflow: hidden; position: relative; background: #f9fafb; }
.shop-review-form__img-slot img { width: 100%; height: 100%; object-fit: cover; }
.shop-review-form__img-remove { position: absolute; top: 2px; right: 2px; width: 18px; height: 18px; background: rgba(0,0,0,0.5); color: #fff; border: none; border-radius: 50%; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
</style>

<div class="shop-review-form">
    <h2 class="shop-review-form__title">구매후기 작성</h2>

    <form class="Mublo-submit-form" data-target="/shop/reviews/store" data-success-msg="후기가 등록되었습니다.">
        <input type="hidden" name="formData[order_detail_id]" value="<?= (int)$orderDetailId ?>">

        <div class="mb-4">
            <label class="form-label fw-semibold">별점</label>
            <div class="shop-review-form__stars" id="starContainer">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star-fill shop-review-form__star" data-value="<?= $i ?>"
                   onclick="ReviewForm.setRating(<?= $i ?>)"></i>
                <?php endfor; ?>
            </div>
            <input type="hidden" name="formData[rating]" id="ratingInput" value="5">
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">후기 내용 <span class="text-danger">*</span></label>
            <textarea name="formData[content]" class="form-control" rows="5" placeholder="상품에 대한 솔직한 후기를 남겨주세요." required minlength="10"></textarea>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">사진 첨부 (최대 3장)</label>
            <div class="shop-review-form__images">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="shop-review-form__img-slot" id="imgSlot<?= $i ?>" onclick="document.getElementById('imgFile<?= $i ?>').click()">
                    <i class="bi bi-plus-lg" style="font-size:1.5rem;color:#9ca3af"></i>
                    <input type="file" id="imgFile<?= $i ?>" name="fileData[image<?= $i ?>]" accept="image/*" style="display:none" onchange="ReviewForm.previewImage(<?= $i ?>, this)">
                </div>
                <?php endfor; ?>
            </div>
            <div class="text-muted mt-1" style="font-size:0.8rem">JPG, PNG, WEBP 파일 (각 5MB 이하)</div>
        </div>

        <button type="submit" class="btn btn-primary w-100">후기 등록하기</button>
    </form>
</div>

<script>
const ReviewForm = {
    setRating(value) {
        document.getElementById('ratingInput').value = value;
        document.querySelectorAll('.shop-review-form__star').forEach((star, idx) => {
            star.classList.toggle('active', idx < value);
        });
    },

    previewImage(slot, input) {
        if (!input.files?.[0]) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            const container = document.getElementById(`imgSlot${slot}`);
            container.innerHTML = `
                <img src="${e.target.result}" alt="미리보기">
                <button class="shop-review-form__img-remove" type="button" onclick="ReviewForm.removeImage(${slot})">
                    <i class="bi bi-x"></i>
                </button>`;
        };
        reader.readAsDataURL(input.files[0]);
    },

    removeImage(slot) {
        const container = document.getElementById(`imgSlot${slot}`);
        container.innerHTML = `<i class="bi bi-plus-lg" style="font-size:1.5rem;color:#9ca3af"></i>
            <input type="file" id="imgFile${slot}" name="fileData[image${slot}]" accept="image/*" style="display:none" onchange="ReviewForm.previewImage(${slot}, this)">`;
    }
};

// 초기 별점 5점 표시
ReviewForm.setRating(5);
</script>
