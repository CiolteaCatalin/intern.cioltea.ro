<?php
require __DIR__.'/includes/bootstrap.php';
require_login();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $type = $_POST['type'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $amount = parse_amount_ro((string)($_POST['amount'] ?? '0'));
            $category = trim($_POST['category'] ?? '');
            $day = (int)($_POST['day_of_month'] ?? 1);
            $startMonth = trim($_POST['start_month'] ?? date('Y-m'));

            if (!in_array($type, ['income','expense'], true) || $name === '' || $amount <= 0 || $day < 1 || $day > 31 || !preg_match('/^\d{4}-\d{2}$/', $startMonth)) {
                throw new RuntimeException('Completează corect denumirea, suma și ziua lunii.');
            }

            $s = $pdo->prepare('INSERT INTO recurring_items(type,name,amount,category,day_of_month,start_month,active) VALUES(?,?,?,?,?,?,1)');
            $s->execute([$type, $name, $amount, $category ?: null, $day, $startMonth]);
            $id = (int)$pdo->lastInsertId();

            $q = $pdo->prepare('SELECT * FROM recurring_items WHERE id=?');
            $q->execute([$id]);
            $item = $q->fetch();
            if ($item && $startMonth <= date('Y-m')) {
                generate_recurring_item_for_period($pdo, $item, date('Y-m'), false);
            }

            $message = 'Recurența a fost adăugată.';
        }

        if ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $s = $pdo->prepare('UPDATE recurring_items SET active = IF(active=1,0,1) WHERE id=?');
            $s->execute([$id]);
            $message = 'Statusul recurenței a fost actualizat.';
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $s = $pdo->prepare('DELETE FROM recurring_items WHERE id=?');
            $s->execute([$id]);
            $message = 'Recurența a fost ștearsă. Tranzacțiile deja create rămân în istoric.';
        }

        if ($action === 'run_now') {
            $id = (int)($_POST['id'] ?? 0);
            $q = $pdo->prepare('SELECT * FROM recurring_items WHERE id=?');
            $q->execute([$id]);
            $item = $q->fetch();
            if ($item) {
                $created = generate_recurring_item_for_period($pdo, $item, date('Y-m'), true);
                $message = $created ? 'Tranzacția pentru luna aceasta a fost creată.' : 'Pentru luna aceasta există deja o tranzacție generată.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

sync_recurring_transactions($pdo);
$items = $pdo->query('SELECT * FROM recurring_items ORDER BY active DESC, type DESC, day_of_month ASC, id DESC')->fetchAll();

$monthlyIncome = 0.0;
$monthlyExpense = 0.0;
foreach ($items as $item) {
    if (!$item['active']) continue;
    if ($item['type'] === 'income') $monthlyIncome += (float)$item['amount'];
    else $monthlyExpense += (float)$item['amount'];
}

$pageTitle = 'Recurențe';
$active = 'money';
require __DIR__.'/includes/layout_top.php';
?>

<?php if ($message): ?><div class="alert success"><?=e($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>

<section class="recurring-summary">
    <article>
        <div class="stat-card__icon income"><?=icon('income')?></div>
        <span>Încasări fixe / lună</span>
        <strong class="pos"><?=money_ro($monthlyIncome)?></strong>
    </article>
    <article>
        <div class="stat-card__icon expense"><?=icon('expense')?></div>
        <span>Cheltuieli fixe / lună</span>
        <strong class="neg"><?=money_ro($monthlyExpense)?></strong>
    </article>
</section>

<section class="recurring-intro">
    <div>
        <span class="eyebrow">Automatizare lunară</span>
        <h2>Nu le mai adăuga manual</h2>
        <p>Salariul, bonurile, avansul, abonamentele și ratele se transformă automat în tranzacții în ziua stabilită.</p>
    </div>
    <button class="btn primary" type="button" data-toggle-recurring-form><?=icon('plus')?> Adaugă</button>
</section>

<section class="settings-card recurring-form-card" id="recurringFormCard" hidden>
    <form method="post" class="form-stack">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="action" value="add">

        <div class="segmented">
            <label><input type="radio" name="type" value="income" checked><span><?=icon('income')?> Încasare</span></label>
            <label><input type="radio" name="type" value="expense"><span><?=icon('expense')?> Cheltuială</span></label>
        </div>

        <label>Denumire
            <div class="input-wrap"><?=icon('repeat')?><input name="name" maxlength="160" required placeholder="Ex: Salariu, Netflix, rată mașină"></div>
        </label>

        <label>Sumă
            <div class="input-wrap money-input"><?=icon('wallet')?><input name="amount" inputmode="decimal" required placeholder="0,00"><span>lei</span></div>
        </label>

        <label>Categorie
            <div class="input-wrap"><?=icon('tag')?><select name="category">
                <option value="">Fără categorie</option>
                <option>Salariu</option><option>Bonuri</option><option>Avans</option>
                <option>Abonamente</option><option>Rate</option><option>Facturi</option>
                <option>Casă</option><option>Transport</option><option>Altele</option>
            </select></div>
        </label>

        <div class="recurring-fields-row">
            <label>Ziua lunii
                <div class="input-wrap"><?=icon('calendar')?><input type="number" name="day_of_month" min="1" max="31" value="1" required></div>
            </label>
            <label>Începând din
                <input type="month" name="start_month" value="<?=e(date('Y-m'))?>" required>
            </label>
        </div>

        <small class="field-note">Dacă alegi ziua 31 într-o lună mai scurtă, se folosește automat ultima zi din acea lună.</small>
        <button class="btn primary wide" type="submit">Salvează recurența</button>
    </form>
</section>

<section class="section-head"><div><span class="eyebrow">Lista ta</span><h2>Recurențe lunare</h2></div></section>

<div class="recurring-list">
<?php if (!$items): ?>
    <div class="empty">Nu ai încă recurențe. Adaugă salariul sau o cheltuială fixă.</div>
<?php endif; ?>

<?php foreach ($items as $item): ?>
    <article class="recurring-row <?=$item['active'] ? '' : 'is-paused'?>">
        <div class="tx-icon <?=$item['type']?>"><?=icon($item['type']==='income'?'income':'expense')?></div>
        <div class="recurring-main">
            <div class="recurring-title-line">
                <strong><?=e($item['name'])?></strong>
                <?php if (!$item['active']): ?><span class="status-pill">Pauză</span><?php endif; ?>
            </div>
            <span><?=e($item['category'] ?: 'Fără categorie')?> · lunar, în ziua <?=e($item['day_of_month'])?></span>
            <b class="<?=$item['type']==='income'?'pos':'neg'?>"><?=$item['type']==='income'?'+':'−'?><?=money_ro($item['amount'])?></b>
        </div>
        <div class="recurring-actions">
            <form method="post">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$item['id']?>">
                <button class="icon-btn mini" type="submit" aria-label="<?=$item['active']?'Pauză':'Activează'?>"><?=icon($item['active']?'pause':'play')?></button>
            </form>
            <form method="post" onsubmit="return confirm('Ștergi această recurență? Tranzacțiile deja create vor rămâne.');">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$item['id']?>">
                <button class="icon-btn mini danger" type="submit" aria-label="Șterge"><?=icon('trash')?></button>
            </form>
        </div>
    </article>
<?php endforeach; ?>
</div>

<section class="settings-card recurring-note">
    <h2>Cum funcționează</h2>
    <p>În ziua stabilită, aplicația creează automat tranzacția pentru luna respectivă. Dacă nu deschizi aplicația chiar în acea zi, o creează prima dată când o deschizi după acea dată. O recurență se generează o singură dată pe lună.</p>
</section>

<?php require __DIR__.'/includes/layout_bottom.php'; ?>
