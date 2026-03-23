<?php
require_once '../includes/session.php';
require_once '../includes/donnees.php';
exiger_connexion();

$u = get_utilisateur_connecte();
if ($u['role'] !== 'client') { header('Location: index.php'); exit; }

// Initialiser le panier en session
if (!isset($_SESSION['panier'])) $_SESSION['panier'] = [];

$message = '';

// Actions panier
$action = $_GET['action'] ?? '';
if ($action === 'ajouter') {
    $type = $_GET['type'] ?? '';
    $id   = (int)($_GET['id'] ?? 0);

    if ($type === 'plat') {
        $item = get_plat_par_id($id);
        $prix = $item ? $item['prix'] : 0;
        $nom  = $item ? $item['nom'] : '';
    } elseif ($type === 'menu') {
        $item = get_menu_par_id($id);
        $prix = $item ? $item['prix_total'] : 0;
        $nom  = $item ? $item['nom'] : '';
    } else {
        $item = null;
    }

    if ($item) {
        $cle = $type . '_' . $id;
        if (isset($_SESSION['panier'][$cle])) {
            $_SESSION['panier'][$cle]['quantite']++;
        } else {
            $_SESSION['panier'][$cle] = [
                'type'     => $type,
                'id'       => $id,
                'nom'      => $nom,
                'prix'     => $prix,
                'quantite' => 1,
            ];
        }
        $message = htmlspecialchars($nom) . ' ajouté au panier !';
    }
}

if ($action === 'retirer') {
    $cle = $_GET['cle'] ?? '';
    unset($_SESSION['panier'][$cle]);
}

if ($action === 'vider') {
    $_SESSION['panier'] = [];
}

// Calcul du total
$total   = 0;
$remise  = $u['remise'];
foreach ($_SESSION['panier'] as $item) {
    $total += $item['prix'] * $item['quantite'];
}
$total_remise = $total * (1 - $remise / 100);

// Traitement commande
$commande_passee = false;
$commande_id_new = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_commande'])) {
    if (empty($_SESSION['panier'])) {
        $message = 'Votre panier est vide.';
    } else {
        $type_commande = $_POST['type_commande'] ?? 'livraison';
        $adresse = ($type_commande === 'livraison') ? ($u['adresse'] ?: '') : 'sur_place';
        $details = ($type_commande === 'livraison') ? ($u['details'] ?: '') : '';
        $date_souhaitee = !empty($_POST['date_souhaitee']) ? $_POST['date_souhaitee'] : date('Y-m-d H:i:s', strtotime('+45 minutes'));

        $articles = [];
        foreach ($_SESSION['panier'] as $item) {
            $articles[] = [
                'type'          => $item['type'],
                'id'            => $item['id'],
                'quantite'      => $item['quantite'],
                'nom'           => $item['nom'],
                'prix_unitaire' => $item['prix'],
            ];
        }

        $nouvelle_commande = [
            'client_id'                => $u['id'],
            'articles'                 => $articles,
            'total'                    => round($total_remise, 2),
            'adresse_livraison'        => $adresse,
            'details_livraison'        => $details,
            'telephone_client'         => $u['telephone'],
            'type'                     => $type_commande,
            'statut'                   => 'en_attente',
            'livreur_id'               => null,
            'date_commande'            => date('Y-m-d H:i:s'),
            'date_livraison_souhaitee' => $date_souhaitee,
            'date_livraison_effective' => null,
            'paiement_statut'          => 'paye',  // Simulé (API CYBank en prod)
            'note_produits'            => null,
            'note_livraison'           => null,
            'commentaire'              => null,
        ];

        $commande_id_new = ajouter_commande($nouvelle_commande);

        // Ajouter des points fidélité (1 point par euro)
        $u_frais = get_utilisateur_par_id($u['id']);
        $u_frais['points_fidelite'] += (int)$total_remise;
        sauvegarder_utilisateur($u_frais);

        $_SESSION['panier'] = [];
        $commande_passee = true;
        $message = "Commande #{$commande_id_new} passée avec succès ! Paiement simulé via CYBank.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Panier - Pizza Nova</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php $base = '../';
require_once '../includes/nav.php'; ?>
<main class="container">
    <h1>Mon Panier 🛒</h1>

    <?php if ($message): ?>
        <p class="message-succes"><?= $message ?></p>
    <?php endif; ?>

    <?php if ($commande_passee): ?>
        <div style="text-align:center; padding:40px;">
            <h2>✅ Commande confirmée !</h2>
            <p>Commande n°<strong><?= $commande_id_new ?></strong> en cours de traitement.</p>
            <a href="profil.php" class="btn-main" style="margin-top:20px;">Suivre ma commande</a>
        </div>
    <?php elseif (empty($_SESSION['panier'])): ?>
        <div style="text-align:center; padding:40px;">
            <p>Votre panier est vide.</p>
            <a href="carte.php" class="btn-main">Voir la carte</a>
        </div>
    <?php else: ?>

    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th>Prix unitaire</th>
                <th>Quantité</th>
                <th>Sous-total</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($_SESSION['panier'] as $cle => $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['nom']) ?> <small>(<?= $item['type'] ?>)</small></td>
                <td><?= number_format($item['prix'], 2) ?> €</td>
                <td><?= $item['quantite'] ?></td>
                <td><?= number_format($item['prix'] * $item['quantite'], 2) ?> €</td>
                <td>
                    <a href="panier.php?action=retirer&cle=<?= urlencode($cle) ?>" class="btn-ok" style="background:#c0392b;">✕</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"><strong>Total brut</strong></td>
                <td colspan="2"><strong><?= number_format($total, 2) ?> €</strong></td>
            </tr>
            <?php if ($remise > 0): ?>
            <tr>
                <td colspan="3"><em>Remise fidélité (<?= $remise ?>%)</em></td>
                <td colspan="2"><em>-<?= number_format($total - $total_remise, 2) ?> €</em></td>
            </tr>
            <tr>
                <td colspan="3"><strong>Total à payer</strong></td>
                <td colspan="2"><strong><?= number_format($total_remise, 2) ?> €</strong></td>
            </tr>
            <?php endif; ?>
        </tfoot>
    </table>

    <a href="panier.php?action=vider" class="btn-ok" style="background:#c0392b; margin:10px 0; display:inline-block;">🗑️ Vider le panier</a>

    <!-- Formulaire commande -->
    <form method="post" action="panier.php" class="form-container" style="margin-top:30px; max-width:600px;">
        <h3>Finaliser ma commande</h3>

        <div class="form-group">
            <label>Type de commande</label>
            <div class="rating-options" style="margin-top:8px;">
                <input type="radio" name="type_commande" id="tc_livraison" value="livraison" checked>
                <label for="tc_livraison">🛵 Livraison</label>
                <input type="radio" name="type_commande" id="tc_emporter" value="emporter">
                <label for="tc_emporter">🏃 À emporter</label>
                <input type="radio" name="type_commande" id="tc_surplace" value="sur_place">
                <label for="tc_surplace">🪑 Sur place</label>
            </div>
        </div>

        <div class="form-group">
            <label for="date_souhaitee">Date/heure souhaitée (laisser vide = ASAP)</label>
            <input type="datetime-local" id="date_souhaitee" name="date_souhaitee">
        </div>

        <?php if ($u['adresse']): ?>
        <p><strong>Adresse de livraison :</strong> <?= htmlspecialchars($u['adresse']) ?><br>
        <small><?= htmlspecialchars($u['details'] ?: '') ?></small></p>
        <?php else: ?>
        <p class="message-erreur">⚠️ Vous n'avez pas d'adresse enregistrée. <a href="profil.php">Mettre à jour en Phase 3</a></p>
        <?php endif; ?>

        <div style="background:var(--bg-douceur); padding:15px; border-radius:8px; margin:15px 0;">
            <strong>💳 Paiement via CYBank</strong>
            <p><small>Le paiement sera traité de manière sécurisée par l'API CYBank.</small></p>
            <p><strong>Total à payer : <?= number_format($total_remise, 2) ?> €</strong></p>
        </div>

        <button type="submit" name="valider_commande" class="btn-main">✅ Confirmer et payer</button>
    </form>
    <?php endif; ?>
</main>
<footer>
    <p>&copy; 2025-2026 Projet Pizza Nova -préING2- Ibrahim, Ikram &amp; Matthieu</p>
</footer>
</body>
</html>
