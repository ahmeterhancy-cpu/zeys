{{-- Ürün formu → Varyantlar: değer seçimi çip görünümünde (CheckboxList üzerine). --}}
<style>
    .zeys-cipler { display: flex !important; flex-wrap: wrap; gap: .5rem; }
    .zeys-cipler .fi-fo-checkbox-list-option-ctn { margin: 0; break-inside: auto; }
    .zeys-cipler .fi-fo-checkbox-list-option {
        position: relative; display: inline-flex; align-items: center; gap: 0;
        min-width: 3rem; justify-content: center; cursor: pointer; user-select: none;
        padding: .45rem .9rem; border-radius: 999px;
        border: 1px solid var(--gray-300); background: #fff; color: var(--gray-700);
        font-size: .875rem; font-weight: 500; line-height: 1.2;
        transition: background-color .12s, border-color .12s, color .12s;
    }
    .zeys-cipler .fi-fo-checkbox-list-option:hover { border-color: var(--primary-500); }
    .zeys-cipler .fi-checkbox-input { position: absolute; opacity: 0; width: 1px; height: 1px; margin: 0; pointer-events: none; }
    .zeys-cipler .fi-fo-checkbox-list-option:has(.fi-checkbox-input:checked) {
        background: var(--primary-600); border-color: var(--primary-600); color: #ffffff;
    }
    /* Seçili çipte renk noktası altın zeminde kaybolmasın */
    .zeys-cipler .fi-fo-checkbox-list-option:has(.fi-checkbox-input:checked) .fi-fo-checkbox-list-option-label span span { box-shadow: 0 0 0 2px #ffffff; }
    .zeys-cipler .fi-fo-checkbox-list-option:has(.fi-checkbox-input:focus-visible) {
        outline: 2px solid var(--primary-500); outline-offset: 2px;
    }
    .zeys-cipler .fi-fo-checkbox-list-option:has(.fi-checkbox-input:disabled) { opacity: .5; cursor: not-allowed; }
    .zeys-cipler .fi-fo-checkbox-list-option-label { font-weight: inherit; color: inherit; }
    .dark .zeys-cipler .fi-fo-checkbox-list-option { background: transparent; border-color: var(--gray-600); color: var(--gray-200); }
    .dark .zeys-cipler .fi-fo-checkbox-list-option:has(.fi-checkbox-input:checked) { background: var(--primary-600); border-color: var(--primary-600); color: #ffffff; }
</style>
