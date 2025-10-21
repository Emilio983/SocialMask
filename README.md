# Technical Status Report - The Social Mask Platform

> **Last Updated:** October 19, 2025  
> **Live Demo:** [https://socialmask.org](https://socialmask.org)  
> **Repository:** [https://github.com/Emilio983/SocialMask](https://github.com/Emilio983/SocialMask)  
> **Current Blockchain:** Polygon Amoy Testnet

---

## Project Overview

We're building a **decentralized social platform** specifically designed to protect investigative journalists and whistleblowers in high-risk environments. The platform combines traditional web2 UX with web3 privacy features to create something that regular people can actually use.

---

## Current Implementation Status

### ✅ What's Working (Approximately 65% Complete)

#### Backend Infrastructure

- ✔️ PHP 8.2 REST API with MySQL 8.0 database
- ✔️ Node.js services for P2P functionality
- ✔️ Nginx web server optimized for VPS deployment

#### Web3 Integration

- ✔️ **SPHE token (ERC-20):** `0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b`
- ✔️ Account Abstraction via ERC-4337 smart accounts
- ✔️ Web3Auth integration for passkey-based authentication
- ✔️ Gelato Relay for gasless transactions
- ✔️ MetaMask and WalletConnect support

#### Decentralized Components

- ✔️ Gun.js relay for P2P database (hosted on Glitch)
- ✔️ IPFS storage via Pinata API (1GB tier)
- ✔️ Signal Protocol for end-to-end encrypted messaging

#### User-Facing Features

- ✔️ User registration and authentication (web3 + traditional)
- ✔️ Community creation and management
- ✔️ Content posting with optional anonymity
- ✔️ Comment threads and discussions
- ✔️ Membership tiers using token holdings
- ✔️ Direct messaging with E2E encryption
- ✔️ Content storage on IPFS
- ✔️ Admin panel for moderation

#### Smart Contracts Deployed

- ✔️ SurveyEscrow contract for community features
- ✔️ Token contract with transfer functionality
- ✔️ Basic governance mechanisms

---

### ⏳ What We're Still Building (Approximately 35% Remaining)

#### Privacy Infrastructure

- ⏳ Anonymous credential system for verified journalists
- ⏳ Zero-knowledge proof circuits for identity verification
- ⏳ Private donation mechanisms
- ⏳ Reputation system without identity exposure

#### Advanced Features

- ⏳ Automated payment distribution for content views
- ⏳ Multi-cryptocurrency donation pools
- ⏳ Cross-chain bridging functionality
- ⏳ Decentralized content moderation
- ⏳ Advanced analytics dashboard

#### Mobile Experience

- ⏳ Progressive Web App optimization
- ⏳ Native mobile apps (future consideration)
- ⏳ Offline-first capabilities

---

## Why We're Reconsidering Our Blockchain Choice

We started building on Polygon for good reasons. It's fast, relatively cheap, has excellent tooling, and a large developer community. For many use cases, it's an ideal choice.

However, as we've implemented our core features and tested with our target users, we've identified a **critical mismatch** between what Polygon offers and what our specific use case requires.

### ⚠️ The Core Issue: Transaction Transparency

Polygon, like most EVM-compatible chains, operates with **full transaction transparency**. Every transaction is publicly visible on block explorers, including:

- Sender addresses
- Recipient addresses
- Transaction amounts
- Transaction timestamps
- Smart contract interactions

> **For typical DeFi applications, NFT platforms, or general-purpose dApps**, this transparency is often desirable. It enables verification, auditability, and trust.

> **For our use case** — protecting journalists in authoritarian regimes — this same transparency becomes a **critical vulnerability**.

---

## Specific Problems for Our Use Case

### Donation Tracking

When someone donates to a journalist covering government corruption, that transaction is **permanently recorded on-chain**. Anyone with basic blockchain knowledge can:

- Identify all supporters of a particular journalist
- Track donation patterns over time
- Correlate wallet addresses with real-world identities through various analysis techniques
- Build comprehensive profiles of financial support networks

### Real-World Threat Model

In countries where we operate, governments have demonstrated capability to:

- Monitor blockchain transactions systematically
- Employ blockchain analytics firms
- Use financial surveillance for intimidation and retaliation
- Target not just journalists but their supporters

### Example Scenario

> A hospital worker in Mexico wants to support a journalist investigating medicine shortages. On Polygon, their donation creates a **permanent public record** linking their wallet to the journalist's wallet. 
>
> Even if they use pseudonyms, sophisticated tracking can potentially reveal real identities through:
> - Exchange KYC records
> - IP address correlation
> - Transaction timing analysis
> - Network graph analysis

---

## What Account Abstraction Does and Doesn't Solve

We implemented **ERC-4337 Account Abstraction** to improve user experience. 

### ✅ It successfully provides:

- Gasless transactions for users
- Social recovery mechanisms
- Better onboarding flow
- Session keys for improved UX

### ❌ What it doesn't provide:

- ❌ Transaction privacy
- ❌ Sender/receiver anonymity
- ❌ Amount obfuscation
- ❌ Relationship hiding

> **The UserOperations are still publicly visible.** The paymaster interactions are traceable. The final on-chain transactions are transparent.

---

## What Our Current Architecture Successfully Protects

It's important to note what we **can** protect with our current design:

### ✅ Content Privacy

Articles and posts stored on IPFS are content-addressed and can be shared **without revealing author identity**.

### ✅ Communication Privacy

Direct messages use **Signal Protocol** for end-to-end encryption. Neither we nor any third party can read these messages.

### ✅ Optional Anonymity

Users can post content **without linking it** to their wallet or real identity.

---

## ⚠️ What Remains Exposed

Any financial transaction on Polygon remains **fully transparent**. This includes:

- Token transfers
- Smart contract calls involving value
- Donation flows
- Payment distributions

---

## 📋 Our Requirements for a Blockchain Solution

We need to be very clear about what we actually need from a blockchain platform. **Not every project needs privacy, but ours does.**

---

### ✅ Must-Have Requirements

#### 1️⃣ Transaction Privacy by Default

We need the ability to conduct financial transactions where **sender, receiver, and amount** are not publicly visible. This isn't optional for our use case.

#### 2. Reasonable Transaction Costs

Our users aren't crypto whales. If it costs **five dollars in fees** to send a **ten dollar donation**, the system doesn't work. We need **sub-dollar transaction costs** for small value transfers.

#### 3. Active Development and Maintenance

We're building something meant to last **years, not months**. We need a blockchain that's actively maintained, has regular updates, and isn't at risk of abandonment.

#### 4. Developer Resources and Documentation

We're good developers, but we're not blockchain researchers. We need **clear documentation**, working examples, and a responsive community when we hit issues.

#### 5. User-Friendly Wallet Options

Our users are **journalists and regular citizens**, not crypto enthusiasts. They need wallets that work on mobile, have reasonable UX, and don't require advanced technical knowledge.

---

### Nice-to-Have Requirements

#### Smart Contract Capability

While not strictly necessary for private donations, smart contracts would enable more sophisticated features like:

- Escrow systems for verified content
- Automated payment splits
- Governance mechanisms
- Conditional transfers

#### Good Liquidity

Users need to be able to convert to/from their local currency or major cryptocurrencies **without significant slippage**.

#### Established Track Record

While we're open to newer projects, something that's been running in production for years gives us more confidence.

#### Cross-Chain Interoperability

The ability to bridge or interact with other chains would give us flexibility and future-proof our architecture.

---

## Architecture Under Consideration

We're evaluating a **hybrid approach** where different components run on different chains based on their privacy requirements.

---

### Proposed Multi-Chain Architecture

#### Public Chain (Possibly Still Polygon)

- Social features (posts, comments)
- Community management
- Non-sensitive smart contracts
- Public content references

#### Privacy Chain (To Be Determined)

- Donation transactions
- Payment distributions
- Financial record keeping
- Sensitive value transfers

#### Decentralized Storage (IPFS)

- Content hosting
- Media files
- Large data objects

#### P2P Layer (Gun.js)

- Real-time features
- Presence information
- Collaborative editing
- Ephemeral data

---

### Backend Service Layer

```javascript
// Conceptual architecture

class BlockchainService {
  constructor() {
    this.publicChain = new PublicChainClient();   // Polygon or similar
    this.privateChain = new PrivacyChainClient(); // TBD
  }

  async handleDonation(from, to, amount) {
    // Route through privacy chain
    return await this.privateChain.sendPrivate(from, to, amount);
  }

  async createPost(userId, content) {
    // Route through public chain
    const ipfsCID = await ipfs.upload(content);
    return await this.publicChain.registerContent(userId, ipfsCID);
  }
}
```

> **The key is that users shouldn't need to understand which chain they're using for what.** The interface abstracts this complexity.

---

## Current Technical Stack

### Infrastructure

#### VPS Hosting

| Component | Specification |
|-----------|--------------|
| **Provider** | Cloudy (Las Vegas datacenter) |
| **OS** | Ubuntu Server 24.04 LTS |
| **RAM** | 1GB (we use external services to compensate) |
| **Storage** | 25GB SSD |
| **CPU** | 1 vCPU |

#### Web Server

- **Nginx** (optimized configuration)
- **PHP 8.2-FPM** (limited to 5 children due to RAM)
- **MySQL 8.0** (128MB buffer pool)
- **Node.js 18+** (managed by PM2)

#### External Services

- **Gun.js relay:** Glitch.com (free tier)
- **IPFS pinning:** Pinata.cloud (1GB free)
- **RPC endpoints:** Public providers

---

### Code Organization

```
thesocialmask/
├── api/                    # PHP REST endpoints
├── backend-node/          # Node.js services
│   ├── src/
│   │   ├── services/
│   │   │   ├── blockchain.js
│   │   │   └── ipfs.js
│   │   └── routes/
├── components/            # Frontend components
├── config/               # Configuration files
├── database/            # SQL schema and migrations
├── escrow-system/       # Smart contracts
├── pages/               # Frontend pages
└── uploads/             # User uploads (migrating to IPFS)
```

---

### Current Database Schema

```sql
-- Core tables implemented
users                    -- User accounts
communities             -- Community structures
posts                   -- Content posts
comments               -- Discussion threads
memberships            -- Membership management
smart_accounts         -- ERC-4337 accounts
user_devices          -- Device management
device_links          -- Device authorization

-- Privacy tables planned
user_private_addresses  -- Privacy chain addresses
private_transactions   -- Transaction metadata
journalist_credentials -- Verified journalist status
anonymous_posts       -- Anonymous content mapping
```

---

## Evaluation Process

We're taking a **methodical approach** to evaluating alternative blockchains.

---

### Week 1-2: Research and Testing

- ✅ Set up testnet access for candidate chains
- ✅ Generate addresses and understand address formats
- ✅ Send test transactions
- Measure actual transaction costs and confirmation times
- Test privacy features (if applicable)
- Review documentation quality
- Assess community responsiveness

---

### Week 3-4: Integration Prototyping

- Build minimal integration with our Node.js backend
- Test wallet libraries
- Implement basic send/receive functionality
- Handle edge cases and errors
- Measure performance under load
- Test mobile wallet compatibility

---

### Week 5-6: User Experience Testing

- Build simple UI for test features
- Run beta tests with non-technical users
- Gather feedback on wallet UX
- Test on various devices and networks
- Identify friction points
- Iterate on implementation

---

### Week 7-8: Security and Production Prep

- Security review of integration code
- Audit transaction flows
- ✅ Test privacy guarantees
- Prepare deployment procedures
- Write documentation
- Plan migration strategy

---

## What We're Open To

We want to be very clear that we're **not ideologically committed** to any particular blockchain. Our commitment is to solving the problem we're trying to solve: **protecting journalists and whistleblowers**.

---

### We're actively interested in:

- Privacy-focused blockchains with transaction shielding
- Newer chains with strong privacy features
- Layer 2 solutions that add privacy to existing chains
- Cross-chain protocols that enable private transfers
- Emerging technologies we might not know about yet

---

### We're willing to consider:

- Chains we haven't worked with before
- Different consensus mechanisms
- Novel cryptographic approaches
- Beta or newer networks if they're well-designed
- Multi-chain architectures

---

### What would make us choose your chain:

| Criteria | Description |
|----------|-------------|
| **Documentation** | Clear documentation and examples |
| **Community** | Active developer community |
| **Proven Features** | Proven privacy features (not vaporware) |
| **Cost** | Reasonable costs for our use case |
| **Support** | Grant programs or technical support |
| **Sustainability** | Long-term sustainability plan |

---

## How You Can Help

### If you work on a blockchain project:

We'd value a conversation. We're trying to make an **informed decision** based on actual requirements, not marketing materials. If you think your chain could work for our use case, let's talk about the specifics.

### If you're a developer with privacy chain experience:

Your insights would be valuable. What worked? What didn't? What surprised you? What would you do differently?

### If you represent a grant program:

We're open to applications if our project fits your criteria. We're building **open source infrastructure** that could benefit the entire ecosystem of whichever chain we choose.

### If you're a journalist or activist who would use this:

Your feedback on what you actually need is crucial. **We're building this for you, not for ourselves.**

---

## Contact and Collaboration

> **GitHub Repository:** [https://github.com/Emilio983/SocialMask](https://github.com/Emilio983/SocialMask)  
> **Live Demo:** [https://socialmask.org](https://socialmask.org)  
> **Project Lead:** Emilio Navarro

---

### We're actively seeking:

- Technical feedback on our architecture
- Partnerships with privacy-focused blockchain projects
- Grant funding to support development
- Beta testers from our target user group
- Collaboration with similar projects

---

## Conclusion

We've built a **substantial platform on Polygon** that works well for many features. We're not abandoning that work. But we've identified that for the **core privacy requirements** of our use case — protecting financial transactions for at-risk journalists — we need something different.

### Our Approach

> **Test thoroughly, evaluate honestly, choose based on requirements rather than hype.**  
> We're not in a rush to make the wrong decision.

If you're working on something that might fit our needs, or if you have experience that could inform our evaluation, we'd genuinely appreciate hearing from you.

---

## Next Update

**Late October 2025** (after completing initial blockchain evaluations)

---

<div align="center">

### Building Privacy Infrastructure for Press Freedom

**Built with ❤️ by journalists, for journalists**

[![GitHub](https://img.shields.io/badge/GitHub-SocialMask-blue?logo=github)](https://github.com/Emilio983/SocialMask)
[![Live Demo](https://img.shields.io/badge/Demo-socialmask.org-green)](https://socialmask.org)
[![Status](https://img.shields.io/badge/Status-Active%20Development-orange)]()

</div>
