<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class OffreFournisseur extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'offres_fournisseur';

    protected $fillable = [
        'ligne_demande_achat_id',
        'fournisseur_id',
        'prix_unitaire',
        'delai_livraison_jours',
        'conditions_paiement_jours',
        'garantie',
        'garantie_mois',
        'offre_technique',
        'commentaire',
        'reference_offre',
        'date_offre',
        'date_validite',
        'score',
        'est_selectionnee',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'prix_unitaire' => 'decimal:0',
        'delai_livraison_jours' => 'integer',
        'conditions_paiement_jours' => 'integer',
        'garantie_mois' => 'integer',
        'score' => 'decimal:2',
        'est_selectionnee' => 'boolean',
        'date_offre' => 'date',
        'date_validite' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        // Calculer le score après création ou mise à jour
        static::saved(function ($model) {
            $model->calculerScore();
        });
    }

    public function ligneDemandeAchat(): BelongsTo
    {
        return $this->belongsTo(LigneDemandeAchat::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function isConforme(): bool
    {
        return $this->offre_technique === 'CONFORME';
    }

    public function getPrixFormateAttribute(): string
    {
        return number_format($this->prix_unitaire ?? 0, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantTotalAttribute(): float
    {
        $ligne = $this->ligneDemandeAchat;
        return $ligne ? $this->prix_unitaire * $ligne->quantite : 0;
    }

    public function getMontantTotalFormateAttribute(): string
    {
        return number_format($this->montant_total, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Calculer le score de l'offre basé sur les critères de pondération
     */
    public function calculerScore(): void
    {
        // Si offre non conforme, score = 0
        if (!$this->isConforme()) {
            $this->updateQuietly(['score' => 0]);
            return;
        }

        // Récupérer toutes les offres conformes de la même ligne
        $offresConformes = self::where('ligne_demande_achat_id', $this->ligne_demande_achat_id)
            ->where('offre_technique', 'CONFORME')
            ->get();

        if ($offresConformes->isEmpty()) {
            return;
        }

        // Récupérer les pondérations
        $ligne = $this->ligneDemandeAchat;
        $societeId = $ligne?->demandeAchat?->societe_id;

        $criteres = DB::table('criteres_ponderation')
            ->where('societe_id', $societeId)
            ->where('actif', true)
            ->pluck('poids', 'code');

        // Valeurs par défaut si pas de critères définis
        $poidsPrix = $criteres['PRIX'] ?? 40;
        $poidsDelai = $criteres['DELAI'] ?? 25;
        $poidsPaiement = $criteres['PAIEMENT'] ?? 15;
        $poidsGarantie = $criteres['GARANTIE'] ?? 10;
        $poidsTechnique = $criteres['TECHNIQUE'] ?? 10;

        // Calculer les valeurs min/max pour normalisation
        $minPrix = $offresConformes->min('prix_unitaire');
        $maxPrix = $offresConformes->max('prix_unitaire');
        $minDelai = $offresConformes->min('delai_livraison_jours');
        $maxDelai = $offresConformes->max('delai_livraison_jours');
        $minPaiement = $offresConformes->min('conditions_paiement_jours');
        $maxPaiement = $offresConformes->max('conditions_paiement_jours');
        $minGarantie = $offresConformes->min('garantie_mois') ?? 0;
        $maxGarantie = $offresConformes->max('garantie_mois') ?? 0;

        // Calculer les scores pour chaque offre conforme
        foreach ($offresConformes as $offre) {
            $score = 0;

            // Score Prix (plus bas = mieux) - inversé
            if ($maxPrix > $minPrix) {
                $scorePrix = 100 - (($offre->prix_unitaire - $minPrix) / ($maxPrix - $minPrix) * 100);
            } else {
                $scorePrix = 100;
            }
            $score += ($scorePrix * $poidsPrix / 100);

            // Score Délai (plus court = mieux) - inversé
            if ($maxDelai > $minDelai) {
                $scoreDelai = 100 - (($offre->delai_livraison_jours - $minDelai) / ($maxDelai - $minDelai) * 100);
            } else {
                $scoreDelai = 100;
            }
            $score += ($scoreDelai * $poidsDelai / 100);

            // Score Paiement (plus long = mieux)
            if ($maxPaiement > $minPaiement) {
                $scorePaiement = (($offre->conditions_paiement_jours - $minPaiement) / ($maxPaiement - $minPaiement) * 100);
            } else {
                $scorePaiement = 100;
            }
            $score += ($scorePaiement * $poidsPaiement / 100);

            // Score Garantie (plus long = mieux)
            if ($maxGarantie > $minGarantie) {
                $scoreGarantie = (($offre->garantie_mois ?? 0 - $minGarantie) / ($maxGarantie - $minGarantie) * 100);
            } else {
                $scoreGarantie = 100;
            }
            $score += ($scoreGarantie * $poidsGarantie / 100);

            // Score Technique (conforme = 100%)
            $score += $poidsTechnique; // Déjà conforme donc 100%

            // Mettre à jour le score
            $offre->updateQuietly(['score' => round($score, 2)]);
        }
    }

    /**
     * Recalculer les scores de toutes les offres d'une ligne
     */
    public static function recalculerScoresLigne(string $ligneId): void
    {
        $offres = self::where('ligne_demande_achat_id', $ligneId)->get();
        foreach ($offres as $offre) {
            $offre->calculerScore();
        }
    }
}
