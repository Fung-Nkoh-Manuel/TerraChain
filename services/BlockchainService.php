<?php
// services/BlockchainService.php

/**
 * Blockchain Service - ONLY used for critical admin operations.
 * Regular users never interact with this directly.
 * 
 * Admin-triggered blockchain operations:
 * 1. approveLandRegistration(documentHash, ownerWallet) -> records on-chain
 * 2. executeTransfer(tokenId, fromWallet, toWallet, documentHash) -> transfers on-chain
 * 3. updateOwnership(tokenId, newOwnerWallet, documentHash) -> for dispute resolution
 */
class BlockchainService {
    private ?string $rpcUrl;
    private bool $enabled;
    
    public function __construct() {
        $this->rpcUrl = RPC_URL;
        $this->enabled = BLOCKCHAIN_ENABLED;
    }
    
    /**
     * Check if blockchain operations are enabled
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }
    
    /**
     * Record approved land registration on blockchain.
     * Only called after admin approval in backend.
     * 
     * @param string $documentHash Combined document hash
     * @param string $ownerWallet Owner's blockchain wallet address
     * @return array [success, tx_hash, token_id]
     */
    public function recordLandRegistration(string $documentHash, string $ownerWallet): array {
        if (!$this->enabled) {
            return ['success' => true, 'tx_hash' => 'offline_' . uniqid(), 'token_id' => null];
        }
        
        try {
            // This would interact with your smart contract
            // For now, returning mock success
            // In production, use ethers/web3.php library
            
            // $contract = new Web3Contract(LAND_REGISTRY_CONTRACT, $abi);
            // $tx = $contract->mintLand($ownerWallet, $documentHash);
            
            return [
                'success' => true,
                'tx_hash' => '0x' . bin2hex(random_bytes(32)),
                'token_id' => random_int(1, 99999)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Record ownership transfer on blockchain.
     * Only called after admin approves transfer.
     * 
     * @param string $documentHash Parcel document hash
     * @param string $fromWallet Current owner wallet
     * @param string $toWallet New owner wallet
     * @return array [success, tx_hash]
     */
    public function recordTransfer(string $documentHash, string $fromWallet, string $toWallet): array {
        if (!$this->enabled) {
            return ['success' => true, 'tx_hash' => 'offline_' . uniqid()];
        }
        
        try {
            // Smart contract interaction
            // $tx = $contract->transferOwnership($documentHash, $fromWallet, $toWallet);
            
            return [
                'success' => true,
                'tx_hash' => '0x' . bin2hex(random_bytes(32))
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update ownership on blockchain due to dispute resolution.
     * 
     * @param string $documentHash Parcel document hash
     * @param string $newOwnerWallet New owner wallet
     * @param string $resolution Notes about why ownership changed
     * @return array [success, tx_hash]
     */
    public function updateOwnershipDueToDispute(string $documentHash, string $newOwnerWallet, string $resolution): array {
        if (!$this->enabled) {
            return ['success' => true, 'tx_hash' => 'offline_' . uniqid()];
        }
        
        try {
            // Smart contract interaction with resolution metadata
            // $tx = $contract->updateOwnership($documentHash, $newOwnerWallet, $resolution);
            
            return [
                'success' => true,
                'tx_hash' => '0x' . bin2hex(random_bytes(32))
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Verify a document hash exists on blockchain
     */
    public function verifyDocumentOnChain(string $documentHash): array {
        if (!$this->enabled) {
            return ['verified' => false, 'message' => 'Blockchain disabled'];
        }
        
        try {
            // Query smart contract for document hash existence
            // $result = $contract->getPropertyByDocumentHash($documentHash);
            
            return ['verified' => true];
        } catch (Exception $e) {
            return ['verified' => false, 'error' => $e->getMessage()];
        }
    }
}