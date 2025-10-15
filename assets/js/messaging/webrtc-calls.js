/**
 * ============================================
 * WEBRTC CALLS SYSTEM
 * ============================================
 * P2P voice, video calls and screen sharing
 */

class WebRTCCalls {
    constructor(e2eMessaging) {
        this.e2eMessaging = e2eMessaging;
        this.gun = null;
        this.localStream = null;
        this.remoteStream = null;
        this.peerConnection = null;
        this.currentCall = null;
        
        // WebRTC configuration
        this.config = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' }
            ]
        };
        
        // Call state
        this.callState = {
            IDLE: 'idle',
            CALLING: 'calling',
            RINGING: 'ringing',
            ACTIVE: 'active',
            ENDED: 'ended'
        };
        
        this.state = this.callState.IDLE;
        
        // Initialize Gun.js for signaling
        this.initSignaling();
    }

    /**
     * Initialize WebSocket for signaling (replaces Gun.js)
     */
    initSignaling() {
        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${wsProtocol}//${window.location.host}/ws/signaling`;
        
        this.ws = new WebSocket(wsUrl);
        
        this.ws.onopen = () => {
            console.log('✅ WebRTC signaling connected');
            // Register user
            this.ws.send(JSON.stringify({
                type: 'register',
                userId: this.e2eMessaging.userId
            }));
        };
        
        this.ws.onmessage = async (event) => {
            try {
                const signal = JSON.parse(event.data);
                
                switch (signal.type) {
                    case 'offer':
                        await this.handleOffer(signal);
                        break;
                    case 'answer':
                        await this.handleAnswer(signal);
                        break;
                    case 'ice-candidate':
                        await this.handleIceCandidate(signal);
                        break;
                    case 'end-call':
                        await this.handleEndCall(signal);
                        break;
                }
            } catch (error) {
                console.error('Error handling WebSocket message:', error);
            }
        };
        
        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
        };
        
        this.ws.onclose = () => {
            console.log('WebSocket closed, attempting reconnect...');
            setTimeout(() => this.initSignaling(), 3000);
        };
    }
    
    /**
     * Send signaling message via WebSocket
     */
    sendSignal(recipientId, signalType, data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                type: signalType,
                from: this.e2eMessaging.userId,
                to: recipientId,
                data
            }));
        } else {
            console.error('WebSocket not connected');
        }
    }

    /**
     * Start voice call
     */
    async startVoiceCall(recipientId) {
        return this.startCall(recipientId, { audio: true, video: false });
    }

    /**
     * Start video call
     */
    async startVideoCall(recipientId) {
        return this.startCall(recipientId, { audio: true, video: true });
    }

    /**
     * Start call with specific constraints
     */
    async startCall(recipientId, constraints) {
        try {
            if (this.state !== this.callState.IDLE) {
                throw new Error('Already in a call');
            }
            
            this.state = this.callState.CALLING;
            
            // Get local media
            this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // Create peer connection
            this.peerConnection = new RTCPeerConnection(this.config);
            
            // Add local stream to peer connection
            this.localStream.getTracks().forEach(track => {
                this.peerConnection.addTrack(track, this.localStream);
            });
            
            // Handle ICE candidates
            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.sendSignal(recipientId, {
                        type: 'ice-candidate',
                        candidate: event.candidate
                    });
                }
            };
            
            // Handle remote stream
            this.peerConnection.ontrack = (event) => {
                this.remoteStream = event.streams[0];
                this.onRemoteStream(this.remoteStream);
            };
            
            // Handle connection state
            this.peerConnection.onconnectionstatechange = () => {
                console.log('Connection state:', this.peerConnection.connectionState);
                
                if (this.peerConnection.connectionState === 'connected') {
                    this.state = this.callState.ACTIVE;
                    this.onCallConnected();
                } else if (this.peerConnection.connectionState === 'disconnected' || 
                           this.peerConnection.connectionState === 'failed') {
                    this.endCall();
                }
            };
            
            // Create offer
            const offer = await this.peerConnection.createOffer();
            await this.peerConnection.setLocalDescription(offer);
            
            // Send offer to recipient
            this.sendSignal(recipientId, {
                type: 'offer',
                offer: offer,
                callType: constraints.video ? 'video' : 'audio',
                callerId: this.e2eMessaging.userId
            });
            
            // Store call data
            this.currentCall = {
                recipientId,
                callType: constraints.video ? 'video' : 'audio',
                startTime: Date.now()
            };
            
            // Notify UI
            this.onCallStarted(recipientId, constraints.video);
            
            console.log('✅ Call initiated');
            return true;
        } catch (error) {
            console.error('❌ Failed to start call:', error);
            this.endCall();
            throw error;
        }
    }

    /**
     * Answer incoming call
     */
    async answerCall() {
        try {
            if (!this.currentCall || this.state !== this.callState.RINGING) {
                throw new Error('No incoming call');
            }
            
            // Get local media
            const constraints = {
                audio: true,
                video: this.currentCall.callType === 'video'
            };
            
            this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // Add local stream to peer connection
            this.localStream.getTracks().forEach(track => {
                this.peerConnection.addTrack(track, this.localStream);
            });
            
            // Create answer
            const answer = await this.peerConnection.createAnswer();
            await this.peerConnection.setLocalDescription(answer);
            
            // Send answer
            this.sendSignal(this.currentCall.callerId, {
                type: 'answer',
                answer: answer
            });
            
            this.state = this.callState.ACTIVE;
            this.onCallAnswered();
            
            console.log('✅ Call answered');
        } catch (error) {
            console.error('❌ Failed to answer call:', error);
            this.rejectCall();
            throw error;
        }
    }

    /**
     * Reject incoming call
     */
    async rejectCall() {
        if (this.currentCall && this.state === this.callState.RINGING) {
            this.sendSignal(this.currentCall.callerId, {
                type: 'end-call',
                reason: 'rejected'
            });
            
            this.endCall();
            this.onCallRejected();
        }
    }

    /**
     * End current call
     */
    endCall() {
        if (this.state === this.callState.IDLE) return;
        
        // Send end call signal
        if (this.currentCall) {
            const recipientId = this.currentCall.recipientId || this.currentCall.callerId;
            this.sendSignal(recipientId, {
                type: 'end-call',
                reason: 'ended'
            });
        }
        
        // Stop local stream
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }
        
        // Close peer connection
        if (this.peerConnection) {
            this.peerConnection.close();
            this.peerConnection = null;
        }
        
        // Reset state
        const callDuration = this.currentCall ? Date.now() - this.currentCall.startTime : 0;
        this.state = this.callState.IDLE;
        this.currentCall = null;
        this.remoteStream = null;
        
        // Notify UI
        this.onCallEnded(callDuration);
        
        console.log('📞 Call ended');
    }

    /**
     * Toggle mute
     */
    toggleMute() {
        if (!this.localStream) return;
        
        const audioTrack = this.localStream.getAudioTracks()[0];
        if (audioTrack) {
            audioTrack.enabled = !audioTrack.enabled;
            this.onMuteToggled(audioTrack.enabled);
            return audioTrack.enabled;
        }
    }

    /**
     * Toggle video
     */
    toggleVideo() {
        if (!this.localStream) return;
        
        const videoTrack = this.localStream.getVideoTracks()[0];
        if (videoTrack) {
            videoTrack.enabled = !videoTrack.enabled;
            this.onVideoToggled(videoTrack.enabled);
            return videoTrack.enabled;
        }
    }

    /**
     * Start screen sharing
     */
    async startScreenShare() {
        try {
            if (!this.peerConnection) {
                throw new Error('No active call');
            }
            
            // Get screen stream
            const screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: {
                    cursor: 'always'
                },
                audio: false
            });
            
            // Replace video track
            const screenTrack = screenStream.getVideoTracks()[0];
            const sender = this.peerConnection.getSenders().find(s => 
                s.track && s.track.kind === 'video'
            );
            
            if (sender) {
                // Store original video track
                this.originalVideoTrack = sender.track;
                
                // Replace with screen track
                await sender.replaceTrack(screenTrack);
                
                // Handle screen share stopped
                screenTrack.onended = () => {
                    this.stopScreenShare();
                };
                
                this.onScreenShareStarted();
                console.log('🖥️ Screen sharing started');
            }
        } catch (error) {
            console.error('❌ Failed to start screen share:', error);
            throw error;
        }
    }

    /**
     * Stop screen sharing
     */
    async stopScreenShare() {
        try {
            if (!this.peerConnection || !this.originalVideoTrack) return;
            
            const sender = this.peerConnection.getSenders().find(s => 
                s.track && s.track.kind === 'video'
            );
            
            if (sender) {
                await sender.replaceTrack(this.originalVideoTrack);
                this.originalVideoTrack = null;
                
                this.onScreenShareStopped();
                console.log('🖥️ Screen sharing stopped');
            }
        } catch (error) {
            console.error('❌ Failed to stop screen share:', error);
        }
    }

    /**
     * Handle incoming offer
     */
    async handleOffer(signal) {
        try {
            if (this.state !== this.callState.IDLE) {
                // Already in a call, reject
                this.sendSignal(signal.callerId, {
                    type: 'end-call',
                    reason: 'busy'
                });
                return;
            }
            
            this.state = this.callState.RINGING;
            
            // Create peer connection
            this.peerConnection = new RTCPeerConnection(this.config);
            
            // Handle ICE candidates
            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.sendSignal(signal.callerId, {
                        type: 'ice-candidate',
                        candidate: event.candidate
                    });
                }
            };
            
            // Handle remote stream
            this.peerConnection.ontrack = (event) => {
                this.remoteStream = event.streams[0];
                this.onRemoteStream(this.remoteStream);
            };
            
            // Handle connection state
            this.peerConnection.onconnectionstatechange = () => {
                if (this.peerConnection.connectionState === 'connected') {
                    this.state = this.callState.ACTIVE;
                }
            };
            
            // Set remote description
            await this.peerConnection.setRemoteDescription(
                new RTCSessionDescription(signal.offer)
            );
            
            // Store call data
            this.currentCall = {
                callerId: signal.callerId,
                callType: signal.callType,
                startTime: Date.now()
            };
            
            // Notify UI
            this.onIncomingCall(signal.callerId, signal.callType);
            
        } catch (error) {
            console.error('❌ Failed to handle offer:', error);
            this.endCall();
        }
    }

    /**
     * Handle answer
     */
    async handleAnswer(signal) {
        try {
            if (!this.peerConnection) return;
            
            await this.peerConnection.setRemoteDescription(
                new RTCSessionDescription(signal.answer)
            );
            
            console.log('✅ Answer received');
        } catch (error) {
            console.error('❌ Failed to handle answer:', error);
        }
    }

    /**
     * Handle ICE candidate
     */
    async handleIceCandidate(signal) {
        try {
            if (!this.peerConnection) return;
            
            await this.peerConnection.addIceCandidate(
                new RTCIceCandidate(signal.candidate)
            );
        } catch (error) {
            console.error('❌ Failed to handle ICE candidate:', error);
        }
    }

    /**
     * Handle end call
     */
    async handleEndCall(signal) {
        console.log('Call ended by remote:', signal.reason);
        this.endCall();
    }

    /**
     * Send signal via Gun.js
     */
    sendSignal(recipientId, signal) {
        const recipientCalls = this.gun.get(`calls_${recipientId}`);
        recipientCalls.set({
            ...signal,
            from: this.e2eMessaging.userId,
            timestamp: Date.now()
        });
    }

    /**
     * Event handlers (override in implementation)
     */
    onCallStarted(recipientId, isVideo) {
        console.log('Call started:', recipientId, isVideo ? 'video' : 'audio');
    }

    onIncomingCall(callerId, callType) {
        console.log('Incoming call from:', callerId, callType);
    }

    onCallAnswered() {
        console.log('Call answered');
    }

    onCallConnected() {
        console.log('Call connected');
    }

    onCallEnded(duration) {
        console.log('Call ended, duration:', duration);
    }

    onCallRejected() {
        console.log('Call rejected');
    }

    onRemoteStream(stream) {
        console.log('Remote stream received');
    }

    onMuteToggled(enabled) {
        console.log('Mute toggled:', enabled);
    }

    onVideoToggled(enabled) {
        console.log('Video toggled:', enabled);
    }

    onScreenShareStarted() {
        console.log('Screen share started');
    }

    onScreenShareStopped() {
        console.log('Screen share stopped');
    }
}

// Export
window.WebRTCCalls = WebRTCCalls;
