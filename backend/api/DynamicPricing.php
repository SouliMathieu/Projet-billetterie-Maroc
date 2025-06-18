<?php
class DynamicPricing {
    
    public function calculatePrice($base_price, $match_date, $coefficient_multiplicateur = 1.0) {
        $now = new DateTime();
        $days_until_match = $now->diff($match_date)->days;
        
        // Coefficient basé sur la proximité du match
        $proximity_coefficient = 1.0;
        
        if ($days_until_match <= 1) {
            $proximity_coefficient = 1.6; // +60%
        } elseif ($days_until_match <= 3) {
            $proximity_coefficient = 1.4; // +40%
        } elseif ($days_until_match <= 7) {
            $proximity_coefficient = 1.2; // +20%
        } elseif ($days_until_match >= 30) {
            $proximity_coefficient = 0.8; // -20% (early bird)
        }
        
        // Appliquer tous les coefficients
        $final_price = $base_price * $proximity_coefficient * $coefficient_multiplicateur;
        
        return round($final_price, 2);
    }
    
    public function getProximityMessage($days_until_match) {
        if ($days_until_match <= 1) {
            return "⚠️ Prix majoration dernière minute (+60%)";
        } elseif ($days_until_match <= 3) {
            return "⚠️ Prix majoration (+40%)";
        } elseif ($days_until_match <= 7) {
            return "⚠️ Prix majoration (+20%)";
        } elseif ($days_until_match >= 30) {
            return "🎉 Prix Early Bird (-20%)";
        }
        
        return "";
    }
}
?>