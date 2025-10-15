/**
 * Staking Calculator
 * Calculadora de proyecciones y estimaciones de rewards
 */

class StakingCalculator {
    constructor() {
        this.pools = [
            { id: 0, name: 'Flexible', apy: 10, lockDays: 0, multiplier: 100 },
            { id: 1, name: '30 Days', apy: 15, lockDays: 30, multiplier: 120 },
            { id: 2, name: '90 Days', apy: 20, lockDays: 90, multiplier: 150 },
            { id: 3, name: '180 Days', apy: 25, lockDays: 180, multiplier: 200 }
        ];
        
        this.SECONDS_PER_YEAR = 365 * 24 * 60 * 60;
        this.PRECISION = 1e18; // Wei precision
    }

    /**
     * Calculate projected rewards
     * @param {number} amount - Amount to stake
     * @param {number} poolId - Pool ID (0-3)
     * @param {number} days - Number of days to stake
     * @returns {Object} Projection data
     */
    calculateProjection(amount, poolId, days) {
        const pool = this.pools[poolId];
        if (!pool) throw new Error('Invalid pool ID');

        const seconds = days * 24 * 60 * 60;
        const rewardRate = this.calculateRewardRate(pool.apy, pool.multiplier);
        
        // Calculate rewards
        const rewards = (amount * rewardRate * seconds) / this.PRECISION;
        const totalReturn = amount + rewards;
        const roi = (rewards / amount) * 100;

        // Calculate effective APY considering lock period
        const effectiveAPY = this.calculateEffectiveAPY(pool.apy, days);

        return {
            pool: pool.name,
            poolId: pool.id,
            stakedAmount: amount,
            stakingPeriod: days,
            baseAPY: pool.apy,
            effectiveAPY: effectiveAPY,
            estimatedRewards: rewards,
            totalReturn: totalReturn,
            roi: roi,
            dailyRewards: rewards / days,
            weeklyRewards: (rewards / days) * 7,
            monthlyRewards: (rewards / days) * 30
        };
    }

    /**
     * Calculate reward rate (per second)
     */
    calculateRewardRate(apy, multiplier) {
        // Convert APY to reward rate per second
        const baseRate = (apy / 100) / this.SECONDS_PER_YEAR;
        return baseRate * (multiplier / 100) * this.PRECISION;
    }

    /**
     * Calculate effective APY considering compound frequency
     */
    calculateEffectiveAPY(nominalAPY, days) {
        // Assuming daily compounding
        const n = days;
        const r = nominalAPY / 100;
        return ((Math.pow(1 + r / n, n) - 1) * 100);
    }

    /**
     * Compare all pools for given amount and period
     */
    compareAllPools(amount, days) {
        return this.pools.map(pool => {
            try {
                return this.calculateProjection(amount, pool.id, days);
            } catch (error) {
                return null;
            }
        }).filter(p => p !== null);
    }

    /**
     * Calculate break-even point between two pools
     */
    calculateBreakEven(amount, poolId1, poolId2) {
        const pool1 = this.pools[poolId1];
        const pool2 = this.pools[poolId2];

        if (!pool1 || !pool2) throw new Error('Invalid pool IDs');

        // Calculate days needed for pool2 to surpass pool1
        // Considering lock periods
        const maxDays = Math.max(pool1.lockDays, pool2.lockDays);
        
        for (let days = maxDays; days <= 365; days++) {
            const proj1 = this.calculateProjection(amount, poolId1, days);
            const proj2 = this.calculateProjection(amount, poolId2, days);

            if (proj2.totalReturn > proj1.totalReturn) {
                return {
                    breakEvenDays: days,
                    pool1: proj1,
                    pool2: proj2
                };
            }
        }

        return null; // Pool2 never surpasses pool1 in 1 year
    }

    /**
     * Calculate what-if scenarios
     */
    calculateScenarios(baseAmount, poolId) {
        const scenarios = [
            { multiplier: 0.5, label: 'Conservative (50%)' },
            { multiplier: 1.0, label: 'Expected (100%)' },
            { multiplier: 1.5, label: 'Optimistic (150%)' },
            { multiplier: 2.0, label: 'Best Case (200%)' }
        ];

        const periods = [30, 90, 180, 365];

        return scenarios.map(scenario => ({
            label: scenario.label,
            amount: baseAmount * scenario.multiplier,
            projections: periods.map(days => {
                const proj = this.calculateProjection(
                    baseAmount * scenario.multiplier, 
                    poolId, 
                    days
                );
                return {
                    days: days,
                    rewards: proj.estimatedRewards,
                    total: proj.totalReturn
                };
            })
        }));
    }

    /**
     * Calculate optimal strategy
     */
    calculateOptimalStrategy(amount, targetDays) {
        const comparisons = this.compareAllPools(amount, targetDays);
        
        // Sort by ROI
        comparisons.sort((a, b) => b.roi - a.roi);

        return {
            optimal: comparisons[0],
            alternatives: comparisons.slice(1),
            recommendation: this.getRecommendation(comparisons[0], targetDays)
        };
    }

    /**
     * Get recommendation based on projection
     */
    getRecommendation(projection, days) {
        const { poolId, roi } = projection;

        if (days < 30) {
            return {
                message: 'Consider Flexible pool for short-term staking',
                reasoning: 'No lock period, withdraw anytime'
            };
        } else if (days >= 30 && days < 90) {
            return {
                message: 'Best option: 30 Days pool',
                reasoning: '15% APY with reasonable lock period'
            };
        } else if (days >= 90 && days < 180) {
            return {
                message: 'Best option: 90 Days pool',
                reasoning: '20% APY, good balance of returns and flexibility'
            };
        } else {
            return {
                message: 'Best option: 180 Days pool',
                reasoning: 'Highest APY (25%) for long-term staking'
            };
        }
    }

    /**
     * Calculate impermanent loss (if applicable to liquidity pools)
     */
    calculateImpermanentLoss(initialPrice, currentPrice, amount) {
        // Simplified IL calculation
        const priceRatio = currentPrice / initialPrice;
        const il = 2 * Math.sqrt(priceRatio) / (1 + priceRatio) - 1;
        const loss = amount * Math.abs(il);

        return {
            impermanentLoss: il * 100, // Percentage
            lossAmount: loss,
            priceChange: ((currentPrice - initialPrice) / initialPrice) * 100
        };
    }

    /**
     * Calculate compound interest
     */
    calculateCompoundInterest(principal, apy, years, compoundFrequency = 365) {
        const rate = apy / 100;
        const amount = principal * Math.pow(1 + rate / compoundFrequency, compoundFrequency * years);
        const interest = amount - principal;

        return {
            finalAmount: amount,
            totalInterest: interest,
            effectiveAPY: ((amount / principal - 1) * 100)
        };
    }

    /**
     * Format number for display
     */
    formatNumber(number, decimals = 4) {
        return parseFloat(number).toFixed(decimals).replace(/\.?0+$/, '');
    }

    /**
     * Format percentage
     */
    formatPercentage(number, decimals = 2) {
        return parseFloat(number).toFixed(decimals) + '%';
    }

    /**
     * Export calculation to JSON
     */
    exportCalculation(calculation) {
        return JSON.stringify(calculation, null, 2);
    }

    /**
     * Generate chart data for visualization
     */
    generateChartData(amount, poolId, maxDays = 365) {
        const dataPoints = [];
        const interval = Math.floor(maxDays / 50); // 50 data points

        for (let day = 0; day <= maxDays; day += interval) {
            const proj = this.calculateProjection(amount, poolId, day || 1);
            dataPoints.push({
                day: day,
                rewards: proj.estimatedRewards,
                total: proj.totalReturn
            });
        }

        return {
            labels: dataPoints.map(p => `Day ${p.day}`),
            rewards: dataPoints.map(p => p.rewards),
            totals: dataPoints.map(p => p.total)
        };
    }

    /**
     * Calculate tax implications (if applicable)
     */
    calculateTaxImpact(rewards, taxRate) {
        const tax = rewards * (taxRate / 100);
        const netRewards = rewards - tax;

        return {
            grossRewards: rewards,
            taxAmount: tax,
            netRewards: netRewards,
            taxRate: taxRate
        };
    }

    /**
     * Calculate risk-adjusted returns
     */
    calculateRiskAdjustedReturns(amount, poolId, riskFactor = 1.0) {
        const projection = this.calculateProjection(amount, poolId, 365);
        const adjustedRewards = projection.estimatedRewards * riskFactor;
        const adjustedROI = (adjustedRewards / amount) * 100;

        return {
            ...projection,
            riskFactor: riskFactor,
            adjustedRewards: adjustedRewards,
            adjustedROI: adjustedROI,
            riskLevel: this.getRiskLevel(riskFactor)
        };
    }

    /**
     * Get risk level label
     */
    getRiskLevel(riskFactor) {
        if (riskFactor >= 1.2) return 'Low Risk';
        if (riskFactor >= 1.0) return 'Medium Risk';
        if (riskFactor >= 0.8) return 'High Risk';
        return 'Very High Risk';
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = StakingCalculator;
}
