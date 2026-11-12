<?php /** @var string $action @var bool $linked @var string $intro */ ?>
<div class="modal fade" id="deleteQuote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="<?= esc($action, 'attr') ?>" id="deleteQuoteForm"><?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-trash text-danger"></i> <span id="deleteQuoteTitle">Offerte verwijderen</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button></div>
            <div class="modal-body">
                <p class="mb-2" id="deleteQuoteIntro"><?= esc($intro) ?></p>
                <div class="form-check" id="deleteQuoteMb" <?= $linked ? '' : 'hidden' ?>>
                    <input class="form-check-input" type="checkbox" name="moneybird" value="1" id="delMb" checked>
                    <label class="form-check-label" for="delMb">Ook in Moneybird verwijderen<br><span class="small text-muted">Alleen als hij daar nog concept is; verstuurde offertes blijven staan.</span></label>
                </div>
                <p class="small text-muted mt-2 mb-0">Apparaten die je uit de offerte hebt aangemaakt blijven bestaan.</p>
                <div id="deleteQuoteIds"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger"><i class="bi bi-trash"></i> Verwijderen</button>
            </div>
        </form>
    </div></div>
</div>
