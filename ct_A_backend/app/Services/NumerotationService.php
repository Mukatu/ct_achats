<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumerotationService
{
    /**
     * Génère le prochain numéro pour un type de document
     * 
     * Formats:
     * - EB: NNNN/AA - EB (ex: 0728/25 - EB)
     * - DA: NNNN/AA - DA (ex: 0640/25 - DA)
     * - DAC: NNNN/AA - DAC (ex: 0640/25 - DAC)
     * - BC: NNN/AA (ex: 001/25)
     * - BR: BR-NNNN/AA (ex: BR-0001/25)
     * - FACT: FACT-NNNNNN/AA (ex: FACT-000001/25)
     */
    public function genererNumero(string $societeId, string $typeDocument): string
    {
        $annee = now()->year;
        $anneeShort = substr($annee, -2);

        return DB::transaction(function () use ($societeId, $typeDocument, $annee, $anneeShort) {
            // Récupérer ou créer la séquence
            $sequence = DB::table('sequences')
                ->where('societe_id', $societeId)
                ->where('type_document', $typeDocument)
                ->where('annee', $annee)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $config = $this->getConfig($typeDocument);
                
                DB::table('sequences')->insert([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'societe_id' => $societeId,
                    'type_document' => $typeDocument,
                    'annee' => $annee,
                    'dernier_numero' => 1,
                    'prefixe' => $config['prefixe'],
                    'suffixe' => $config['suffixe'],
                    'longueur_numero' => $config['longueur'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $numeroSeq = 1;
                $config = $this->getConfig($typeDocument);
            } else {
                $numeroSeq = $sequence->dernier_numero + 1;
                
                DB::table('sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'dernier_numero' => $numeroSeq,
                        'updated_at' => now(),
                    ]);
                
                $config = [
                    'prefixe' => $sequence->prefixe,
                    'suffixe' => $sequence->suffixe,
                    'longueur' => $sequence->longueur_numero,
                ];
            }

            return $this->formaterNumero($numeroSeq, $anneeShort, $config, $typeDocument);
        });
    }

    protected function getConfig(string $typeDocument): array
    {
        return match($typeDocument) {
            'EB' => ['prefixe' => '', 'suffixe' => ' - EB', 'longueur' => 4],
            'DA' => ['prefixe' => '', 'suffixe' => ' - DA', 'longueur' => 4],
            'DAC' => ['prefixe' => '', 'suffixe' => ' - DAC', 'longueur' => 4],
            'BC' => ['prefixe' => '', 'suffixe' => '', 'longueur' => 3],
            'BR' => ['prefixe' => 'BR-', 'suffixe' => '', 'longueur' => 4],
            'FACT' => ['prefixe' => 'FACT-', 'suffixe' => '', 'longueur' => 6],
            'CTR' => ['prefixe' => 'CTR-', 'suffixe' => '', 'longueur' => 4],
            default => ['prefixe' => '', 'suffixe' => '', 'longueur' => 6],
        };
    }

    protected function formaterNumero(int $numero, string $annee, array $config, string $typeDocument): string
    {
        $numeroFormate = str_pad($numero, $config['longueur'], '0', STR_PAD_LEFT);

        return match($typeDocument) {
            'EB', 'DA', 'DAC' => "{$numeroFormate}/{$annee}{$config['suffixe']}",
            'BC' => "{$numeroFormate}/{$annee}",
            'BR', 'CTR' => "{$config['prefixe']}{$numeroFormate}/{$annee}",
            'FACT' => "{$config['prefixe']}{$numeroFormate}/{$annee}",
            default => "{$config['prefixe']}{$numeroFormate}/{$annee}{$config['suffixe']}",
        };
    }

    /**
     * Extrait le numéro de séquence d'un numéro formaté
     */
    public function extraireNumeroSequence(string $numero): ?int
    {
        // Extraire les chiffres avant le /
        if (preg_match('/(\d+)\//', $numero, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
}
