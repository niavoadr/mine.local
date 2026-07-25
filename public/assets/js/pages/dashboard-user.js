    $(document).ready(function() {
        loadVisitors();
        
        setInterval(function() {
            if ($('#strangers-content').hasClass('active')) {
                loadVisitors();
            }
        }, 10000);
        
        $("#create-visitor-form").on('submit', function(e) {
            e.preventDefault();
            createVisitor();
        });
    });

    function confirmLogout() {
        if (confirm("⚠️ Êtes-vous sûr de vouloir vous déconnecter du tableau de bord ?")) {
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
        
        if (tabName === 'strangers') {
            loadVisitors();
        }
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
