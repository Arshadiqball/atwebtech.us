<?php
/** @var string $id */
?>
        <div class="hp" aria-hidden="true">
          <label for="<?= e($id) ?>-hp">Leave this field empty</label>
          <input type="text" id="<?= e($id) ?>-hp" name="<?= e(Security::HONEYPOT) ?>" value="" tabindex="-1" autocomplete="off" data-lpignore="true">
        </div>
