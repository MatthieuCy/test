<?php
require_once '../includes/session.php';
require_once '../includes/donnees.php';
exiger_connexion();

$u = get_utilisateur_connecte();
$commandes = array_values(get_commandes_client($u['id']));
// Trier par date décroissante
usort($commandes, fn($a, $b) => strcmp($b['date_commande'], $a['date_commande']));

$statut_labels = [
    'en_attente'     => '⏳ En attente',
    'en_preparation' => '👨‍🍳 En préparation',
    'en_livraison'   => '🛵 En livraison',
    'livree'         => '✅ Livrée',
    'annulee'        => '❌ Annulée',
];
$statut_classes = [
    'en_attente'     => 'status-wait',
    'en_preparation' => 'status-prep',
    'en_livraison'   => 'status-delivery',
    'livree'         => 'status-delivered',
    'annulee'        => 'status-cancelled',
];

// Calcul de la remise basée sur les points fidélité
$remise = $u['remise'];
$points = $u['points_fidelite'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Pizza Nova</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php $base = '../';
require_once '../includes/nav.php'; ?>
<main class="profile-container">
    <section class="profile-header">
        <h2>Bienvenue sur votre espace, <?= htmlspecialchars($u['prenom']) ?> !</h2>
        <h3>Gérez vos informations et profitez de vos avantages fidélité.</h3>
    </section>

    <div class="profile-grid">
        <!-- Informations personnelles -->
        <aside class="profile-card">
            <h3>Mes Informations ✎</h3>
            <div class="info-item">
                <div><strong>Nom :</strong> <span><?= htmlspecialchars($u['nom']) ?></span></div>
                <span class="edit-icon" title="Modifiable en Phase 3">✎</span>
            </div>
            <div class="info-item">
                <div><strong>Prénom :</strong> <span><?= htmlspecialchars($u['prenom']) ?></span></div>
                <span class="edit-icon" title="Modifiable en Phase 3">✎</span>
            </div>
            <div class="info-item">
                <div><strong>Email :</strong> <span><?= htmlspecialchars($u['login']) ?></span></div>
                <span class="edit-icon" title="Modifiable en Phase 3">✎</span>
            </div>
            <div class="info-item">
                <div><strong>Téléphone :</strong> <span><?= htmlspecialchars($u['telephone'] ?: 'Non renseigné') ?></span></div>
                <span class="edit-icon" title="Modifiable en Phase 3">✎</span>
            </div>
            <div class="info-item">
                <div><strong>Adresse :</strong> <span><?= htmlspecialchars($u['adresse'] ?: 'Non renseignée') ?></span></div>
                <span class="edit-icon" title="Modifiable en Phase 3">✎</span>
            </div>
            <div class="info-item">
                <div><strong>Détails :</strong> <span><?= htmlspecialchars($u['details'] ?: 'Aucun') ?></span></div>
                <span class="edit-icon" title="Modifiable en Phase 3">✎</span>
            </div>
            <p class="note-phase"><em>✏️ La modification sera effective en Phase 3.</em></p>
        </aside>

        <!-- Points fidélité -->
        <section class="profile-card loyalty">
            <h3>Points Fidélité 🏆</h3>
            <div class="points-box">
                <strong><?= $points ?></strong> points cumulés
            </div>
            <?php if ($remise > 0): ?>
                <p><em>Vous avez droit à <strong><?= $remise ?>%</strong> de remise sur votre prochaine commande !</em></p>
            <?php else: ?>
                <p><em>Continuez à commander pour débloquer des remises !</em></p>
            <?php endif; ?>
            <p class="statut-compte">Statut : <span class="badge-statut badge-<?= $u['statut'] ?>"><?= ucfirst($u['statut']) ?></span></p>
        </section>

        <!-- Historique des commandes -->
        <section class="profile-card history">
            <h3>Mes Commandes</h3>
            <?php if (empty($commandes)): ?>
                <p>Vous n'avez pas encore passé de commande. <a href="carte.php">Voir la carte</a></p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Date</th>
                        <th>Détails</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <?php // Note disponible seulement pour commandes livrées non encore notées ?>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($commandes as $cmd): ?>
                    <tr>
                        <td>#<?= $cmd['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($cmd['date_commande'])) ?></td>
                        <td>
                            <?php foreach ($cmd['articles'] as $art): ?>
                                <?= $art['quantite'] ?>x <?= htmlspecialchars($art['nom']) ?><br>
                            <?php endforeach; ?>
                        </td>
                        <td><?= number_format($cmd['total'], 2) ?> €</td>
                        <td>
                            <span class="<?= $statut_classes[$cmd['statut']] ?? '' ?>">
                                <?= $statut_labels[$cmd['statut']] ?? $cmd['statut'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($cmd['statut'] === 'livree' && $cmd['note_produits'] === null): ?>
                                <a href="notation.php?commande_id=<?= $cmd['id'] ?>" class="btn-ok">Noter</a>
                            <?php elseif ($cmd['note_produits'] !== null): ?>
                                ⭐ <?= $cmd['note_produits'] ?>/5
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </div>

    <?php if (in_array($u['role'], ['client'])): ?>
    <div style="text-align:center; margin-top:30px;">
        <a href="carte.php" class="btn-main">🍕 Commander maintenant</a>
    </div>
    <?php endif; ?>
</main>
<footer>
    <p>&copy; 2025-2026 Projet Pizza Nova -préING2- Ibrahim, Ikram &amp; Matthieu</p>
</footer>
</body>
</html>
