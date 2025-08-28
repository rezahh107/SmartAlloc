<?php
declare(strict_types=1);

?>
<div id="smartalloc-override-modal" style="display:none">
    <p>
        <label for="smartalloc-override-mentor"><?php echo esc_html__( 'Mentor ID', 'smartalloc' ); ?></label>
        <input type="number" id="smartalloc-override-mentor" />
    </p>
    <p>
        <label for="smartalloc-override-notes"><?php echo esc_html__( 'Notes', 'smartalloc' ); ?></label>
        <textarea id="smartalloc-override-notes"></textarea>
    </p>
    <p>
        <button type="button" class="button button-primary" id="smartalloc-override-submit"><?php echo esc_html__( 'Submit', 'smartalloc' ); ?></button>
    </p>
</div>
