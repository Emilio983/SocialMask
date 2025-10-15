/**
 * ============================================
 * ABUSE DETECTOR
 * ============================================
 * AI-powered abuse detection
 */

class AbuseDetector {
    constructor() {
        this.patterns = {
            spam: [],
            bot: [],
            harassment: []
        };
        
        this.userBehavior = {
            postCount: 0,
            commentCount: 0,
            lastActionTime: null,
            actionTimings: []
        };
        
        this.init();
    }

    async init() {
        console.log('🤖 Initializing Abuse Detector...');
        
        // Load spam patterns
        await this.loadPatterns();
        
        // Monitor user behavior
        this.monitorBehavior();
        
        console.log('✅ Abuse Detector initialized');
    }

    async loadPatterns() {
        // Common spam patterns
        this.patterns.spam = [
            /buy\s+(now|today|here)/i,
            /click\s+(here|this|now)/i,
            /(free|cheap|discount)\s+(viagra|cialis)/i,
            /make\s+\$\d+/i,
            /work\s+from\s+home/i,
            /limited\s+time\s+offer/i,
            /act\s+now/i,
            /visit\s+our\s+website/i
        ];
        
        // Bot detection patterns
        this.patterns.bot = [
            /^(.)\1{10,}$/, // Repeated characters
            /[a-z0-9]{50,}/i // Long random strings
        ];
        
        // Harassment patterns
        this.patterns.harassment = [
            /\b(kill\s+yourself|kys)\b/i,
            /\b(stupid|idiot|moron|dumb)\b.*\b(you|ur|your)\b/i,
            /\b(hate|despise)\s+you\b/i
        ];
    }

    /**
     * Monitor user behavior
     */
    monitorBehavior() {
        // Track post submissions
        document.addEventListener('submit', (e) => {
            if (e.target.matches('[data-post-form]')) {
                this.trackAction('post');
            }
        });
        
        // Track comments
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-submit-comment]')) {
                this.trackAction('comment');
            }
        });
    }

    /**
     * Track user action
     */
    trackAction(actionType) {
        const now = Date.now();
        
        if (actionType === 'post') {
            this.userBehavior.postCount++;
        } else if (actionType === 'comment') {
            this.userBehavior.commentCount++;
        }
        
        // Track timing
        if (this.userBehavior.lastActionTime) {
            const timeDiff = now - this.userBehavior.lastActionTime;
            this.userBehavior.actionTimings.push(timeDiff);
            
            // Keep only last 10 timings
            if (this.userBehavior.actionTimings.length > 10) {
                this.userBehavior.actionTimings.shift();
            }
        }
        
        this.userBehavior.lastActionTime = now;
        
        // Check for bot behavior
        this.checkBotBehavior();
    }

    /**
     * Check for bot behavior
     */
    checkBotBehavior() {
        const timings = this.userBehavior.actionTimings;
        
        if (timings.length < 5) return;
        
        // Calculate average timing
        const avg = timings.reduce((a, b) => a + b, 0) / timings.length;
        
        // Calculate standard deviation
        const variance = timings.reduce((sum, time) => {
            return sum + Math.pow(time - avg, 2);
        }, 0) / timings.length;
        const stdDev = Math.sqrt(variance);
        
        // Very consistent timing = likely bot
        if (stdDev < 100 && avg < 2000) {
            console.warn('🤖 Bot behavior detected');
            this.reportAbusePattern('bot', 0.85);
        }
        
        // Very fast posting = likely bot
        if (avg < 500) {
            console.warn('🤖 Suspiciously fast actions');
            this.reportAbusePattern('bot', 0.90);
        }
    }

    /**
     * Analyze content for spam
     */
    analyzeSpam(content) {
        let spamScore = 0;
        const matches = [];
        
        // Check against patterns
        for (const pattern of this.patterns.spam) {
            if (pattern.test(content)) {
                spamScore += 0.2;
                matches.push(pattern.toString());
            }
        }
        
        // Check for excessive links
        const linkCount = (content.match(/https?:\/\//g) || []).length;
        if (linkCount > 3) {
            spamScore += 0.3;
            matches.push('Excessive links');
        }
        
        // Check for repeated content
        const words = content.toLowerCase().split(/\s+/);
        const uniqueWords = new Set(words);
        const repetitionRatio = 1 - (uniqueWords.size / words.length);
        if (repetitionRatio > 0.5) {
            spamScore += 0.2;
            matches.push('High repetition');
        }
        
        // Check for all caps
        const capsRatio = (content.match(/[A-Z]/g) || []).length / content.length;
        if (capsRatio > 0.7 && content.length > 20) {
            spamScore += 0.15;
            matches.push('Excessive caps');
        }
        
        return {
            isSpam: spamScore >= 0.5,
            score: Math.min(spamScore, 1.0),
            matches
        };
    }

    /**
     * Analyze content for harassment
     */
    analyzeHarassment(content) {
        let harassmentScore = 0;
        const matches = [];
        
        // Check against patterns
        for (const pattern of this.patterns.harassment) {
            if (pattern.test(content)) {
                harassmentScore += 0.4;
                matches.push(pattern.toString());
            }
        }
        
        // Check for excessive profanity
        const profanityCount = this.countProfanity(content);
        if (profanityCount > 2) {
            harassmentScore += 0.2;
            matches.push('Excessive profanity');
        }
        
        // Check for targeted language
        if (/\byou\b.*\b(are|r)\b/i.test(content)) {
            harassmentScore += 0.1;
        }
        
        return {
            isHarassment: harassmentScore >= 0.5,
            score: Math.min(harassmentScore, 1.0),
            matches
        };
    }

    /**
     * Count profanity
     */
    countProfanity(content) {
        const profanityList = [
            'fuck', 'shit', 'bitch', 'ass', 'damn',
            'crap', 'hell', 'bastard', 'dick', 'piss'
        ];
        
        let count = 0;
        const lowerContent = content.toLowerCase();
        
        for (const word of profanityList) {
            const regex = new RegExp(`\\b${word}\\b`, 'g');
            const matches = lowerContent.match(regex);
            if (matches) {
                count += matches.length;
            }
        }
        
        return count;
    }

    /**
     * Validate content before posting
     */
    async validateContent(content) {
        const spamAnalysis = this.analyzeSpam(content);
        const harassmentAnalysis = this.analyzeHarassment(content);
        
        if (spamAnalysis.isSpam) {
            console.warn('🚫 Spam detected:', spamAnalysis);
            this.reportAbusePattern('spam', spamAnalysis.score);
            return {
                valid: false,
                reason: 'spam',
                message: 'Your content appears to be spam. Please review our community guidelines.'
            };
        }
        
        if (harassmentAnalysis.isHarassment) {
            console.warn('🚫 Harassment detected:', harassmentAnalysis);
            this.reportAbusePattern('harassment', harassmentAnalysis.score);
            return {
                valid: false,
                reason: 'harassment',
                message: 'Your content may violate our harassment policy. Please be respectful.'
            };
        }
        
        return {
            valid: true
        };
    }

    /**
     * Report abuse pattern
     */
    async reportAbusePattern(patternType, confidence) {
        try {
            await fetch('/api/moderation/report-abuse-pattern.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    pattern_type: patternType,
                    confidence_score: confidence,
                    evidence: {
                        behavior: this.userBehavior,
                        timestamp: Date.now()
                    }
                })
            });
        } catch (error) {
            console.error('Failed to report abuse pattern:', error);
        }
    }

    /**
     * Check if user is rate limited
     */
    checkRateLimit() {
        const now = Date.now();
        const oneHour = 60 * 60 * 1000;
        
        // Reset counts if more than 1 hour
        if (this.userBehavior.lastActionTime && 
            (now - this.userBehavior.lastActionTime) > oneHour) {
            this.userBehavior.postCount = 0;
            this.userBehavior.commentCount = 0;
        }
        
        // Check limits
        if (this.userBehavior.postCount > 20) {
            return {
                limited: true,
                message: 'You have reached the hourly post limit. Please try again later.'
            };
        }
        
        if (this.userBehavior.commentCount > 50) {
            return {
                limited: true,
                message: 'You have reached the hourly comment limit. Please try again later.'
            };
        }
        
        return {
            limited: false
        };
    }
}

// Export
window.AbuseDetector = AbuseDetector;

// Initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.abuseDetector = new AbuseDetector();
    });
} else {
    window.abuseDetector = new AbuseDetector();
}
