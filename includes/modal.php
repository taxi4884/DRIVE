<?php

if (!function_exists('renderModal')) {
    function renderModal(string $id, string $title, string $contentPath, array $data = []): void
    {
        if ($data) {
            extract($data, EXTR_SKIP);
        }
        ?>
        <div class="modal" id="<?= htmlspecialchars($id) ?>">
            <div class="modal-content">
                <span class="close" onclick="closeModal('<?= htmlspecialchars($id) ?>')">&times;</span>
                <h2><?= htmlspecialchars($title) ?></h2>
                <?php include $contentPath; ?>
            </div>
        </div>
        <?php
    }
}
