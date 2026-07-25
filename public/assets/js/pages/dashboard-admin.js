    $(document).ready(function() {
        loadVisitors();
        loadBlacklist();
        loadBlacklistStats();
        loadIntrusions();
        loadIntrusionStats();
        
        setInterval(function() {
            if ($('#visitor-section').hasClass('active')) {
                loadVisitors();
            }
            if ($('#intrusion-section').hasClass('active')) {
                loadIntrusions();
                loadIntrusionStats();
            }
        }, 10000);
        
        $("#create-visitor-form").on('submit', function(e) {
            e.preventDefault();
            createVisitor();
        });
        $("#add-blacklist-btn").on('click', addToBlacklist);
        $("#intrusion-filter-btn").on('click', loadIntrusions);
        
        $('.subsection-btn').on('click', function() {
            // Masquer le résumé des identifiants visiteur lors du changement de sous-section
            $('#visitor-credentials').addClass('d-none');

            const section = $(this).data('section');
            $('.subsection-btn').removeClass('active');
            $(this).addClass('active');
            $('.subsection-content').removeClass('active');
            $(`#${section}-section`).addClass('active');
            
            if (section === 'visitor') {
                loadVisitors();
            } else if (section === 'blacklist') {
                loadBlacklist();
                loadBlacklistStats();
            } else if (section === 'intrusion') {
                loadIntrusions();
                loadIntrusionStats();
            }
        });
    });

    function confirmLogout() {
        if (confirm("⚠️ Êtes-vous sûr de vouloir vous déconnecter du portail d'administration ?")) {
            window.location.href = "logout.php";
        }
    }

    function switchTab(tabName) {
        // Masquer le résumé des identifiants visiteur lors du changement d'onglet
        $('#visitor-credentials').addClass('d-none');

        const sections = document.querySelectorAll('.content-section');
        sections.forEach(section => section.classList.remove('active'));
        
        const tabs = document.querySelectorAll('.nav-tab');
        tabs.forEach(tab => tab.classList.remove('active'));
        
        const targetSection = document.getElementById(tabName + '-content');
        if (targetSection) {
            targetSection.classList.add('active');
        }
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('active');
        }

        if (tabName === 'alerts') {
            loadSystemAlerts();
        }
        if (tabName === 'strangers') {
            loadVisitors();
        }
    }

    function loadSystemAlerts() {
        const container = document.getElementById('alerts-log-container');
        if (!container) return;
        container.innerHTML = '<div class="text-center py-4 text-warning"><i class="fa-solid fa-spinner fa-spin me-2"></i>Chargement des journaux de sécurité...</div>';
        fetch('api/alerts.php')
            .then(response => response.text())
            .then(data => {
                container.innerHTML = `<div class="log-console">${data || 'Aucune alerte récente.'}</div>`;
            })
            .catch(err => {
                container.innerHTML = `<div class="alert alert-danger">Erreur lors de la récupération des alertes: ${err}</div>`;
            });
    }

    // ==================== VISITOR FUNCTIONS ====================
    function createVisitor() {
        const username = $('#visitor-username').val();
        const duration = $('#visitor-duration').val();
        
        $.ajax({
            url: 'api/visitor-manager.php',
            type: 'POST',
            data: {
                action: 'create_visitor',
                username: username,
                duration: duration
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#res-username').text(response.data.username);
                    $('#res-password').text(response.data.password);
                    $('#res-expires').text(response.data.expires_at);
                    $('#visitor-credentials').removeClass('d-none');
                    $('#create-visitor-form')[0].reset();
                    loadVisitors();
                } else {
                    alert('Erreur: ' + response.message);
                }
            },
            error: function() {
                alert('Une erreur est survenue lors de la création du visiteur.');
            }
        });
    }

    function loadVisitors() {
        $.ajax({
            url: 'api/visitor-manager.php',
            type: 'POST',
            data: { action: 'get_visitors' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayVisitors(response.data);
                } else {
                    $('#visitor-table-body').html('<tr><td colspan="8" class="text-center text-danger py-4">Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function() {
                $('#visitor-table-body').html('<tr><td colspan="8" class="text-center text-danger py-4">Une erreur de communication est survenue.</td></tr>');
            }
        });
    }
    
    function displayVisitors(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucun visiteur enregistré.</td></tr>';
        } else {
            records.forEach(function(record) {
                const statusClass = record.status === 'active' ? 'badge bg-success text-dark fw-bold' : 'badge bg-danger';
                const statusLabel = record.status === 'active' ? 'Actif' : 'Expiré';
                
                html += `
                    <tr>
                        <td class="fw-semibold text-white">${record.username}</td>
                        <td><code>${record.mac_address}</code></td>
                        <td>${record.ip_address}</td>
                        <td><small class="text-muted">${record.creator_name}</small></td>
                        <td>${record.display_start}</td>
                        <td>${record.display_end}</td>
                        <td>${record.display_duration}</td>
                        <td><span class="${statusClass}">${statusLabel}</span></td>
                    </tr>
                `;
            });
        }
        $('#visitor-table-body').html(html);
    }

    function calculateTimeLeft(startTime, stopTime) {
        if (stopTime) return '';
        const sessionDuration = 2 * 60;
        const now = new Date();
        const start = new Date(startTime);
        const elapsedSeconds = (now - start) / 1000;
        const remainingSeconds = sessionDuration - elapsedSeconds;
        if (remainingSeconds <= 0) return 'Expiré';
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = Math.floor(remainingSeconds % 60);
        return `${minutes}min ${seconds}s`;
    }
    
    function formatDuration(seconds) {
        if (seconds === null || seconds === undefined || isNaN(seconds)) return 'N/A';
        const totalSeconds = parseInt(seconds);
        if (totalSeconds < 0) return 'N/A';
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const sec = totalSeconds % 60;
        let result = [];
        if (hours > 0) result.push(`${hours}h`);
        if (minutes > 0 || hours > 0) result.push(`${minutes}min`);
        result.push(`${sec}s`);
        return result.join(' ');
    }

    // ==================== BLACKLIST FUNCTIONS ====================
    function loadBlacklist() {
        $('#blacklist-table').html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2 text-warning"></i>Chargement de la liste noire...</td></tr>');
        
        $.ajax({
            url: 'api/blacklist.php',
            type: 'POST',
            data: { action: 'get_blacklist' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayBlacklist(response.data);
                } else {
                    $('#blacklist-table').html('<tr><td colspan="6" class="text-center text-danger py-4">Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function() {
                $('#blacklist-table').html('<tr><td colspan="6" class="text-center text-danger py-4">Une erreur de communication est survenue.</td></tr>');
            }
        });
    }

    function displayBlacklist(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucun appareil bloqué pour le moment.</td></tr>';
        } else {
            records.forEach(function(record) {
                html += `
                    <tr>
                        <td><code>${record.mac_address}</code></td>
                        <td>${record.ip_address || 'N/A'}</td>
                        <td><span class="badge bg-danger px-3 py-2">${record.reason}</span></td>
                        <td>${record.blocked_date}</td>
                        <td><span class="badge bg-warning text-dark fw-bold">${record.blocked_attempts || 0}</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-success fw-semibold" onclick="unblockDevice('${record.mac_address}')" style="border-radius: 8px;">
                                <i class="fas fa-unlock me-1"></i> Débloquer
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#blacklist-table').html(html);
    }

    function loadBlacklistStats() {
        $.ajax({
            url: 'api/blacklist.php',
            type: 'POST',
            data: { action: 'get_stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#blocked-count').text(response.data.total || 0);
                    $('#blocked-today').text(response.data.today || 0);
                }
            }
        });
    }

    function addToBlacklist() {
        const mac = $('#blacklist-mac').val();
        const reason = $('#blacklist-reason').val();
        
        if (!mac || !reason) {
            alert('Veuillez remplir tous les champs');
            return;
        }
        
        $.ajax({
            url: 'api/blacklist.php',
            type: 'POST',
            data: {
                action: 'add_blacklist',
                mac_address: mac,
                reason: reason
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('✅ Appareil ajouté à la liste noire');
                    $('#blacklist-mac').val('');
                    $('#blacklist-reason').val('');
                    loadBlacklist();
                    loadBlacklistStats();
                } else {
                    alert('❌ Erreur: ' + response.message);
                }
            }
        });
    }

    function unblockDevice(mac) {
        if (confirm('Voulez-vous vraiment débloquer cet appareil ?')) {
            $.ajax({
                url: 'api/blacklist.php',
                type: 'POST',
                data: {
                    action: 'remove_blacklist',
                    mac_address: mac
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('✅ Appareil débloqué avec succès');
                        loadBlacklist();
                        loadBlacklistStats();
                    } else {
                        alert('❌ Erreur: ' + response.message);
                    }
                }
            });
        }
    }

    // ==================== INTRUSION FUNCTIONS ====================
    function loadIntrusions() {
        const severity = $('#intrusion-severity').val();
        const type = $('#intrusion-type').val();
        const date = $('#intrusion-date').val();
        
        $('#intrusion-table').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2 text-warning"></i>Chargement des intrusions...</td></tr>');
        
        $.ajax({
            url: 'api/intrusions.php',
            type: 'POST',
            data: {
                action: 'get_intrusions',
                severity: severity,
                type: type,
                date: date
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayIntrusions(response.data);
                } else {
                    $('#intrusion-table').html('<tr><td colspan="7" class="text-center text-danger py-4">Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function() {
                $('#intrusion-table').html('<tr><td colspan="7" class="text-center text-danger py-4">Une erreur de communication est survenue. Vérifiez la configuration des logs.</td></tr>');
            }
        });
    }

    function displayIntrusions(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucune intrusion détectée.</td></tr>';
        } else {
            records.forEach(function(record) {
                const severityBadge = getSeverityBadge(record.severity);
                const sourceInfoBadge = getSourceInfoBadge(record.source_info);
                
                html += `
                    <tr>
                        <td>${record.timestamp}</td>
                        <td><span class="badge bg-info text-dark fw-semibold">${record.type}</span></td>
                        <td>${severityBadge}</td>
                        <td>
                            <small><strong>IP:</strong> ${record.ip_address || 'N/A'}</small><br/>
                            <small><strong>MAC:</strong> <code>${record.mac_address || 'N/A'}</code></small>
                        </td>
                        <td><small>${record.description}</small></td>
                        <td>${sourceInfoBadge}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-danger fw-semibold" onclick="blockFromIntrusion('${record.mac_address}', '${record.type}')" style="border-radius: 8px;">
                                <i class="fas fa-ban me-1"></i> Bloquer
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#intrusion-table').html(html);
    }

    function loadIntrusionStats() {
        $.ajax({
            url: 'api/intrusions.php',
            type: 'POST',
            data: { action: 'get_stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#critical-alerts').text(response.data.critical || 0);
                    $('#medium-alerts').text(response.data.medium || 0);
                    $('#suspicious-attempts').text(response.data.suspicious || 0);
                }
            }
        });
    }

    function getSeverityBadge(severity) {
        const badges = {
            'critical': '<span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i> Critique</span>',
            'high': '<span class="badge bg-danger">Élevée</span>',
            'medium': '<span class="badge bg-warning text-dark fw-bold">Moyenne</span>',
            'low': '<span class="badge bg-info text-dark fw-bold">Faible</span>'
        };
        return badges[severity] || '<span class="badge bg-secondary">Inconnue</span>';
    }

    function getSourceInfoBadge(source) {
        const badges = {
            'Snort': '<span class="badge bg-primary"><i class="fas fa-shield-alt me-1"></i> Snort</span>',
            'Firewall': '<span class="badge bg-secondary"><i class="fas fa-fire me-1"></i> Firewall</span>',
            'Fail2ban': '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i> Fail2ban</span>',
            'Manual': '<span class="badge bg-dark border border-secondary"><i class="fas fa-user me-1"></i> Manuel</span>'
        };
        return badges[source] || '<span class="badge bg-info text-dark">Autre</span>';
    }

    function blockFromIntrusion(mac, type) {
        if (mac === 'N/A') {
            alert('Impossible de bloquer: adresse MAC non disponible');
            return;
        }
        if (confirm('Voulez-vous bloquer cet appareil suite à cette intrusion ?')) {
            $.ajax({
                url: 'api/blacklist.php',
                type: 'POST',
                data: {
                    action: 'add_blacklist',
                    mac_address: mac,
                    reason: 'Intrusion détectée: ' + type
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('✅ Appareil bloqué avec succès');
                        loadIntrusions();
                    } else {
                        alert('❌ Erreur: ' + response.message);
                    }
                }
            });
        }
    }
