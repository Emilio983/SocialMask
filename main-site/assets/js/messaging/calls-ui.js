/**
 * ============================================
 * CALLS UI
 * ============================================
 * User interface for WebRTC calls
 */

class CallsUI {
    constructor(webrtcCalls) {
        this.webrtcCalls = webrtcCalls;
        this.callModal = null;
        this.isMuted = false;
        this.isVideoEnabled = true;
        this.isScreenSharing = false;
        
        this.init();
    }

    /**
     * Initialize UI
     */
    init() {
        // Create call modal
        this.createCallModal();
        
        // Setup event handlers
        this.setupEventHandlers();
        
        // Override WebRTC callbacks
        this.setupCallbacks();
    }

    /**
     * Create call modal
     */
    createCallModal() {
        const modal = document.createElement('div');
        modal.id = 'callModal';
        modal.className = 'call-modal hidden';
        modal.innerHTML = `
            <div class="call-container">
                <div class="video-container">
                    <video id="remoteVideo" autoplay playsinline></video>
                    <video id="localVideo" autoplay playsinline muted></video>
                    <div class="call-info">
                        <div class="caller-name"></div>
                        <div class="call-duration">00:00</div>
                        <div class="call-status"></div>
                    </div>
                </div>
                
                <div class="call-controls">
                    <button class="control-btn mute-btn" title="Mute">
                        <i class="fas fa-microphone"></i>
                    </button>
                    <button class="control-btn video-btn" title="Toggle Video">
                        <i class="fas fa-video"></i>
                    </button>
                    <button class="control-btn screen-btn" title="Share Screen">
                        <i class="fas fa-desktop"></i>
                    </button>
                    <button class="control-btn end-btn" title="End Call">
                        <i class="fas fa-phone-slash"></i>
                    </button>
                </div>
                
                <div class="incoming-call-controls hidden">
                    <button class="answer-btn">
                        <i class="fas fa-phone"></i> Answer
                    </button>
                    <button class="reject-btn">
                        <i class="fas fa-phone-slash"></i> Reject
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.callModal = modal;
    }

    /**
     * Setup event handlers
     */
    setupEventHandlers() {
        // Mute button
        const muteBtn = this.callModal.querySelector('.mute-btn');
        muteBtn.addEventListener('click', () => {
            this.toggleMute();
        });
        
        // Video button
        const videoBtn = this.callModal.querySelector('.video-btn');
        videoBtn.addEventListener('click', () => {
            this.toggleVideo();
        });
        
        // Screen share button
        const screenBtn = this.callModal.querySelector('.screen-btn');
        screenBtn.addEventListener('click', () => {
            this.toggleScreenShare();
        });
        
        // End call button
        const endBtn = this.callModal.querySelector('.end-btn');
        endBtn.addEventListener('click', () => {
            this.endCall();
        });
        
        // Answer button
        const answerBtn = this.callModal.querySelector('.answer-btn');
        answerBtn.addEventListener('click', () => {
            this.answerCall();
        });
        
        // Reject button
        const rejectBtn = this.callModal.querySelector('.reject-btn');
        rejectBtn.addEventListener('click', () => {
            this.rejectCall();
        });
    }

    /**
     * Setup callbacks
     */
    setupCallbacks() {
        this.webrtcCalls.onCallStarted = (recipientId, isVideo) => {
            this.showCallModal('calling', isVideo);
            this.startCallDuration();
        };
        
        this.webrtcCalls.onIncomingCall = (callerId, callType) => {
            this.showIncomingCall(callerId, callType);
            this.playRingtone();
        };
        
        this.webrtcCalls.onCallAnswered = () => {
            this.updateCallStatus('connected');
            this.stopRingtone();
        };
        
        this.webrtcCalls.onCallConnected = () => {
            this.updateCallStatus('connected');
        };
        
        this.webrtcCalls.onCallEnded = (duration) => {
            this.hideCallModal();
            this.stopCallDuration();
            this.stopRingtone();
        };
        
        this.webrtcCalls.onRemoteStream = (stream) => {
            const remoteVideo = this.callModal.querySelector('#remoteVideo');
            remoteVideo.srcObject = stream;
        };
        
        this.webrtcCalls.onMuteToggled = (enabled) => {
            this.updateMuteButton(enabled);
        };
        
        this.webrtcCalls.onVideoToggled = (enabled) => {
            this.updateVideoButton(enabled);
        };
        
        this.webrtcCalls.onScreenShareStarted = () => {
            this.updateScreenButton(true);
        };
        
        this.webrtcCalls.onScreenShareStopped = () => {
            this.updateScreenButton(false);
        };
    }

    /**
     * Start voice call
     */
    async startVoiceCall(userId) {
        try {
            await this.webrtcCalls.startVoiceCall(userId);
        } catch (error) {
            this.showError('Failed to start voice call');
        }
    }

    /**
     * Start video call
     */
    async startVideoCall(userId) {
        try {
            await this.webrtcCalls.startVideoCall(userId);
        } catch (error) {
            this.showError('Failed to start video call');
        }
    }

    /**
     * Answer call
     */
    async answerCall() {
        try {
            await this.webrtcCalls.answerCall();
            
            // Show local stream
            const localVideo = this.callModal.querySelector('#localVideo');
            localVideo.srcObject = this.webrtcCalls.localStream;
            
            // Hide incoming controls, show normal controls
            this.callModal.querySelector('.incoming-call-controls').classList.add('hidden');
            this.callModal.querySelector('.call-controls').classList.remove('hidden');
            
            this.startCallDuration();
        } catch (error) {
            this.showError('Failed to answer call');
        }
    }

    /**
     * Reject call
     */
    rejectCall() {
        this.webrtcCalls.rejectCall();
        this.hideCallModal();
    }

    /**
     * End call
     */
    endCall() {
        this.webrtcCalls.endCall();
    }

    /**
     * Toggle mute
     */
    toggleMute() {
        const enabled = this.webrtcCalls.toggleMute();
        this.isMuted = !enabled;
    }

    /**
     * Toggle video
     */
    toggleVideo() {
        const enabled = this.webrtcCalls.toggleVideo();
        this.isVideoEnabled = enabled;
    }

    /**
     * Toggle screen share
     */
    async toggleScreenShare() {
        try {
            if (this.isScreenSharing) {
                await this.webrtcCalls.stopScreenShare();
            } else {
                await this.webrtcCalls.startScreenShare();
            }
            this.isScreenSharing = !this.isScreenSharing;
        } catch (error) {
            this.showError('Failed to toggle screen share');
        }
    }

    /**
     * Show call modal
     */
    showCallModal(status, isVideo) {
        this.callModal.classList.remove('hidden');
        this.updateCallStatus(status);
        
        // Show/hide video elements
        const localVideo = this.callModal.querySelector('#localVideo');
        const remoteVideo = this.callModal.querySelector('#remoteVideo');
        
        if (isVideo) {
            localVideo.classList.remove('hidden');
            remoteVideo.classList.remove('hidden');
            
            // Show local stream
            localVideo.srcObject = this.webrtcCalls.localStream;
        } else {
            localVideo.classList.add('hidden');
            remoteVideo.classList.add('hidden');
        }
        
        // Show call controls
        this.callModal.querySelector('.call-controls').classList.remove('hidden');
        this.callModal.querySelector('.incoming-call-controls').classList.add('hidden');
    }

    /**
     * Show incoming call
     */
    showIncomingCall(callerId, callType) {
        this.callModal.classList.remove('hidden');
        
        const callerName = this.callModal.querySelector('.caller-name');
        callerName.textContent = `Incoming ${callType} call from User ${callerId}`;
        
        this.updateCallStatus('ringing');
        
        // Hide call controls, show incoming controls
        this.callModal.querySelector('.call-controls').classList.add('hidden');
        this.callModal.querySelector('.incoming-call-controls').classList.remove('hidden');
    }

    /**
     * Hide call modal
     */
    hideCallModal() {
        this.callModal.classList.add('hidden');
        
        // Stop local stream
        const localVideo = this.callModal.querySelector('#localVideo');
        if (localVideo.srcObject) {
            localVideo.srcObject.getTracks().forEach(track => track.stop());
            localVideo.srcObject = null;
        }
        
        // Clear remote stream
        const remoteVideo = this.callModal.querySelector('#remoteVideo');
        remoteVideo.srcObject = null;
        
        // Reset state
        this.isMuted = false;
        this.isVideoEnabled = true;
        this.isScreenSharing = false;
    }

    /**
     * Update call status
     */
    updateCallStatus(status) {
        const statusElement = this.callModal.querySelector('.call-status');
        statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);
    }

    /**
     * Update mute button
     */
    updateMuteButton(enabled) {
        const muteBtn = this.callModal.querySelector('.mute-btn');
        const icon = muteBtn.querySelector('i');
        
        if (enabled) {
            icon.className = 'fas fa-microphone';
            muteBtn.classList.remove('active');
        } else {
            icon.className = 'fas fa-microphone-slash';
            muteBtn.classList.add('active');
        }
    }

    /**
     * Update video button
     */
    updateVideoButton(enabled) {
        const videoBtn = this.callModal.querySelector('.video-btn');
        const icon = videoBtn.querySelector('i');
        
        if (enabled) {
            icon.className = 'fas fa-video';
            videoBtn.classList.remove('active');
        } else {
            icon.className = 'fas fa-video-slash';
            videoBtn.classList.add('active');
        }
    }

    /**
     * Update screen button
     */
    updateScreenButton(sharing) {
        const screenBtn = this.callModal.querySelector('.screen-btn');
        
        if (sharing) {
            screenBtn.classList.add('active');
        } else {
            screenBtn.classList.remove('active');
        }
    }

    /**
     * Start call duration timer
     */
    startCallDuration() {
        const durationElement = this.callModal.querySelector('.call-duration');
        let seconds = 0;
        
        this.durationInterval = setInterval(() => {
            seconds++;
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            durationElement.textContent = 
                `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }, 1000);
    }

    /**
     * Stop call duration timer
     */
    stopCallDuration() {
        if (this.durationInterval) {
            clearInterval(this.durationInterval);
            this.durationInterval = null;
        }
    }

    /**
     * Play ringtone
     */
    playRingtone() {
        this.ringtone = new Audio('/assets/sounds/ringtone.mp3');
        this.ringtone.loop = true;
        this.ringtone.play().catch(() => {});
    }

    /**
     * Stop ringtone
     */
    stopRingtone() {
        if (this.ringtone) {
            this.ringtone.pause();
            this.ringtone = null;
        }
    }

    /**
     * Show error
     */
    showError(message) {
        // Use messaging UI toast or similar
        console.error(message);
        alert(message);
    }
}

// Export
window.CallsUI = CallsUI;
