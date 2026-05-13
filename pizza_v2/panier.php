<?php
require_once(__DIR__ . '/includes/session.php');
require_once(__DIR__ . '/includes/donnees.php');
exiger_connexion();

$u = get_utilisateur_connecte();

if ($u['role'] !== 'client') {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

$message = '';

// -------------------------------------------------------
// ACTIONS SUR LE PANIER (GET)
// -------------------------------------------------------
$action = $_GET['action'] ?? '';

if ($action === 'ajouter') {
    $type = $_GET['type'] ?? '';
    $id   = (int)($_GET['id'] ?? 0);
    $item = null;
    $prix = 0;
    $nom  = '';

    if ($type === 'plat') {
        $item = get_plat_par_id($id);
        if ($item) {
            $prix = $item['prix'];
            $nom  = $item['nom'];
        }
    } elseif ($type === 'menu') {
        $item = get_menu_par_id($id);
        if ($item) {
            $prix = $item['prix_total'];
            $nom  = $item['nom'];
        }
    }

    if ($item !== null) {
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

// -------------------------------------------------------
// CALCUL DES TOTAUX
// -------------------------------------------------------
$total           = 0;
$remise_pourcent = $u['remise'] ?? 0;

foreach ($_SESSION['panier'] as $item) {
    $total += $item['prix'] * $item['quantite'];
}

$montant_remise = $total * ($remise_pourcent / 100);
$total_remise   = $total - $montant_remise;

// -------------------------------------------------------
// VALIDATION COMMANDE (POST) — BYPASS CYBANK EN LOCAL
// -------------------------------------------------------
$commande_passee      = false;
$id_nouvelle_commande = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_commande'])) {

    if (empty($_SESSION['panier'])) {
        $message = 'Votre panier est vide.';
    } else {
        $type_choisi = $_POST['type_commande'] ?? 'livraison';
        $date_souhaitee = $_POST['date_souhaitee'] ?? '';

        // Construire l'adresse selon le type
        if ($type_choisi === 'livraison') {
            $adresse_finale  = $u['adresse'] ?: 'Adresse non renseignée';
            $details_finaux  = $u['details'] ?: '';
        } elseif ($type_choisi === 'emporter') {
            $adresse_finale  = 'À emporter';
            $details_finaux  = '';
        } else {
            $adresse_finale  = 'Sur place';
            $details_finaux  = '';
        }

        // Construire la liste des articles
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

        // Créer la commande directement dans le JSON
        $nouvelle_commande = [
            'client_id'                => $u['id'],
            'articles'                 => $articles,
            'total'                    => round($total_remise, 2),
            'adresse_livraison'        => $adresse_finale,
            'details_livraison'        => $details_finaux,
            'telephone_client'         => $u['telephone'] ?? '',
            'type'                     => $type_choisi,
            'statut'                   => 'en_attente',
            'livreur_id'               => null,
            'date_commande'            => date('Y-m-d H:i:s'),
            'date_livraison_souhaitee' => $date_souhaitee ?: null,
            'date_livraison_effective' => null,
            'paiement_statut'          => 'paye',
            'note_produits'            => null,
            'note_livraison'           => null,
            'commentaire'              => null,
        ];

        $id_nouvelle_commande = ajouter_commande($nouvelle_commande);

        // Ajouter des points fidélité (1 point par euro)
        $u_frais = get_utilisateur_par_id($u['id']);
        $u_frais['points_fidelite'] = ($u_frais['points_fidelite'] ?? 0) + (int)$total_remise;
        sauvegarder_utilisateur($u_frais);

        // Vider le panier
        $_SESSION['panier'] = [];
        $commande_passee    = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Panier - Pizza Nova</title>
</head>
<body>
<?php $base = ''; require_once(__DIR__ . '/includes/nav.php'); ?>

<main class="container">
    <h1>Mon Panier 🛒</h1>

    <?php if ($message): ?>
        <p class="message-succes"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($commande_passee): ?>
        <!-- Confirmation commande -->
        <div class="form-container" style="text-align:center;">
            <h2>✅ Commande confirmée !</h2>
            <p>Votre commande n°<strong>#<?= $id_nouvelle_commande ?></strong>
               a été enregistrée et transmise à la cuisine.</p>
            <br>
            <a href="profil.php" class="btn-main" style="margin-right:10px;">
                📋 Suivre ma commande
            </a>
            <a href="carte.php" class="btn-main">
                🍕 Commander encore
            </a>
        </div>

    <?php elseif (empty($_SESSION['panier'])): ?>
        <!-- Panier vide -->
        <div class="form-container" style="text-align:center;">
            <p>Votre panier est vide.</p>
            <br>
            <a href="carte.php" class="btn-main">Voir la carte</a>
        </div>

    <?php else: ?>
        <!-- Tableau du panier -->
        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Prix unitaire</th>
                    <th>Quantité</th>
                    <th>Sous-total</th>
                    <th>Retirer</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($_SESSION['panier'] as $cle => $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['nom']) ?>
                        <small>(<?= $item['type'] ?>)</small>
                    </td>
                    <td><?= number_format($item['prix'], 2) ?> €</td>
                    <td><?= $item['quantite'] ?></td>
                    <td><?= number_format($item['prix'] * $item['quantite'], 2) ?> €</td>
                    <td>
                        <a href="panier.php?action=retirer&cle=<?= urlencode($cle) ?>"
                           class="btn-ok" style="background:#c0392b;">✕</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>Total brut</strong></td>
                    <td colspan="2"><strong><?= number_format($total, 2) ?> €</strong></td>
                </tr>
                <?php if ($remise_pourcent > 0): ?>
                <tr>
                    <td colspan="3"><em>Remise fidélité (<?= $remise_pourcent ?>%)</em></td>
                    <td colspan="2"><em>- <?= number_format($montant_remise, 2) ?> €</em></td>
                </tr>
                <tr>
                    <td colspan="3"><strong>Total à payer</strong></td>
                    <td colspan="2">
                        <strong style="color:var(--terracotta);font-size:18px;">
                            <?= number_format($total_remise, 2) ?> €
                        </strong>
                    </td>
                </tr>
                <?php endif; ?>
            </tfoot>
        </table>

        <a href="panier.php?action=vider"
           class="btn-ok"
           style="background:#c0392b;margin:15px 0;display:inline-block;">
            🗑️ Vider le panier
        </a>

        <!-- Formulaire de finalisation -->
        <form method="post" action="panier.php" class="form-container">
            <h3>Finaliser ma commande</h3>

            <div class="form-group">
                <label>Type de commande</label>
                <div class="rating-options" style="margin-top:8px;">
                    <input type="radio" name="type_commande"
                           id="tc_livraison" value="livraison" checked>
                    <label for="tc_livraison">🛵 Livraison</label>

                    <input type="radio" name="type_commande"
                           id="tc_emporter" value="emporter">
                    <label for="tc_emporter">🏃 À emporter</label>

                    <input type="radio" name="type_commande"
                           id="tc_surplace" value="sur_place">
                    <label for="tc_surplace">🪑 Sur place</label>
                </div>
            </div>

            <div class="form-group">
                <label for="date_souhaitee">
                    Date/heure souhaitée (laisser vide = immédiatement)
                </label>
                <input type="datetime-local" id="date_souhaitee" name="date_souhaitee">
            </div>

            <?php if ($u['adresse']): ?>
                <p style="margin-bottom:15px;">
                    <strong>📍 Adresse de livraison :</strong>
                    <?= htmlspecialchars($u['adresse']) ?><br>
                    <small><?= htmlspecialchars($u['details'] ?: '') ?></small>
                </p>
            <?php else: ?>
                <p class="message-erreur">
                    ⚠️ Vous n'avez pas d'adresse enregistrée.
                    Modifiez votre profil pour en ajouter une.
                </p>
            <?php endif; ?>

            <!-- Résumé paiement -->
            <div style="background:var(--bg-douceur);padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid var(--or);">
                <strong>💳 Récapitulatif</strong><br>
                Total à payer :
                <strong style="color:var(--terracotta);font-size:18px;">
                    <?= number_format($total_remise, 2) ?> €
                </strong>
                <?php if ($remise_pourcent > 0): ?>
                    <br><small>(remise de <?= $remise_pourcent ?>% appliquée)</small>
                <?php endif; ?>
            </div>

            <button type="submit" name="valider_commande" class="btn-main">
                ✅ Confirmer la commande
            </button>
        </form>

    <?php endif; ?>
</main>

<footer>
    <p>&copy; 2025-2026 Projet Pizza Nova -préING2- Ibrahim, Ikram &amp; Matthieu</p>
</footer>
</body>
</html>
