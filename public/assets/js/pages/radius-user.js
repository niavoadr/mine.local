        $(document).ready(function() {
            loadDevices();
            loadStats();
        });

        // Charger la liste des appareils
        function loadDevices() {
            $.post('../api/radius-devices.php', {action: 'get_devices'}, function(response) {
                if (response.success) {
                    displayDevices(response.data);
                } else {
                    $('#devicesTable').html('<tr><td colspan="4" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Erreur: ' + response.error + '</td></tr>');
                }
            }, 'json').fail(function() {
                $('#devicesTable').html('<tr><td colspan="4" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Erreur de communication avec le serveur</td></tr>');
            });
        }

        // Afficher les appareils
        function displayDevices(devices) {
            let html = '';
            if (devices.length === 0) {
                html = '<tr><td colspan="4" class="text-center py-4 text-muted">Aucun appareil configuré pour le moment.</td></tr>';
            } else {
                devices.forEach(function(device) {
                    const departmentColors = {
                        'finance': 'badge-finance',
                        'rh': 'badge-rh', 
                        'daj': 'badge-daj',
                        'communication': 'badge-communication',
                        'sg': 'badge-sg'
                    };
                    
                    html += `
                        <tr>
                            <td class="mac-address">${device.mac_address}</td>
                            <td><span class="badge ${departmentColors[device.department] || 'bg-secondary'} department-badge">${device.department.toUpperCase()}</span></td>
                            <td>${device.group}</td>
                            <td><span class="badge bg-dark border border-secondary px-3 py-1">${device.bandwidth}</span></td>
                        </tr>
                    `;
                });
            }
            $('#devicesTable').html(html);
        }

        // Charger les statistiques
        function loadStats() {
            $.post('../api/radius-devices.php', {action: 'get_devices'}, function(response) {
                if (response.success) {
                    const devices = response.data;
                    const stats = {};
                    
                    devices.forEach(function(device) {
                        stats[device.department] = (stats[device.department] || 0) + 1;
                    });
                    
                    let html = `
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>Total</h6><h4>${devices.length}</h4></div></div>
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>Finance</h6><h4>${stats.finance || 0}</h4></div></div>
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>RH</h6><h4>${stats.rh || 0}</h4></div></div>
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>DAJ</h6><h4>${stats.daj || 0}</h4></div></div>
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>Comm</h6><h4>${stats.communication || 0}</h4></div></div>
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>SG</h6><h4>${stats.sg || 0}</h4></div></div>
                    `;
                    
                    $('#stats').html(html);
                }
            }, 'json');
        }
