        function formatToIETF(mac) {
            let clean = mac.replace(/[^a-fA-F0-9]/g, '');
            if (clean.length === 12) {
                return clean.match(/.{1,2}/g).join('-').toUpperCase();
            }
            return mac; 
        }

        $(document).ready(function() {
            loadDevices();
            loadStats();
        });

        function loadDevices() {
            $.post('../api/radius-devices.php', {action: 'get_devices'}, function(response) {
                if (response.success) {
                    displayDevices(response.data);
                } else {
                    $('#devicesTable').html('<tr><td colspan="5" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Erreur: ' + response.error + '</td></tr>');
                }
            }, 'json').fail(function() {
                $('#devicesTable').html('<tr><td colspan="5" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Erreur de communication</td></tr>');
            });
        }

        function displayDevices(devices) {
            let html = '';
            if (devices.length === 0) {
                html = '<tr><td colspan="5" class="text-center py-4 text-muted">Aucun appareil configuré dans RADIUS.</td></tr>';
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
                            <td class="text-end">
                                <button class="btn btn-danger btn-sm" onclick="deleteDevice('${device.mac_address}')" style="border-radius: 8px;">
                                    <i class="fas fa-trash me-1"></i> Supprimer
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#devicesTable').html(html);
        }

        $('#addDeviceForm').submit(function(e) {
            e.preventDefault();
            
            let macInput = $('#mac_address').val();
            const macIETF = formatToIETF(macInput);
            const department = $('#department').val();
            
            if(macIETF.length !== 17) {
                alert("L'adresse MAC saisie est invalide. Veuillez saisir 12 caractères hexadécimaux.");
                return;
            }

            $('.loading').show();
            $('button[type="submit"]').prop('disabled', true);
            
            $.post('../api/radius-devices.php', {
                action: 'add_device',
                mac_address: macIETF,
                department: department
            }, function(response) {
                if (response.success) {
                    alert('✅ Appareil ajouté avec succès (Format IETF : ' + macIETF + ')');
                    $('#addDeviceForm')[0].reset();
                    loadDevices();
                    loadStats();
                } else {
                    alert('❌ Erreur: ' + response.error);
                }
            }, 'json').fail(function() {
                alert('❌ Erreur de communication avec le serveur');
            }).always(function() {
                $('.loading').hide();
                $('button[type="submit"]').prop('disabled', false);
            });
        });

        function deleteDevice(mac) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet appareil ?\n\nMAC: ' + mac)) {
                $.post('../api/radius-devices.php', {
                    action: 'delete_device',
                    mac_address: mac
                }, function(response) {
                    if (response.success) {
                        alert('✅ Appareil supprimé !');
                        loadDevices();
                        loadStats();
                    } else {
                        alert('❌ Erreur: ' + response.error);
                    }
                }, 'json');
            }
        }

        function loadStats() {
            $.post('../api/radius-devices.php', {action: 'get_devices'}, function(response) {
                if (response.success) {
                    const devices = response.data;
                    const stats = {
                        finance: 0,
                        rh: 0,
                        daj: 0,
                        communication: 0,
                        sg: 0,
                        dsi: 0,
                        dircab: 0
                    };
                    
                    devices.forEach(function(device) {
                        if (stats.hasOwnProperty(device.department)) {
                            stats[device.department]++;
                        }
                    });
                    
                    $('#totalDevices').text(devices.length);
                    
                    const chartData = [
                        { label: 'Finance', value: stats.finance, color: '#2196F3' },
                        { label: 'RH', value: stats.rh, color: '#FF9800' },
                        { label: 'DAJ', value: stats.daj, color: '#9C27B0' },
                        { label: 'Comm', value: stats.communication, color: '#F44336' },
                        { label: 'SG', value: stats.sg, color: '#00BCD4' },
                        { label: 'DSI', value: stats.dsi, color: '#4CAF50' },
                        { label: 'DirCab', value: stats.dircab, color: '#FFC107' }
                    ];
                    
                    drawPieChart(chartData, devices.length);
                    updateLegend(chartData, devices.length);
                }
            }, 'json');
        }

        function drawPieChart(data, total) {
            const canvas = document.getElementById('pieChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = 80;

            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (total === 0) {
                ctx.beginPath();
                ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
                ctx.fillStyle = '#22222a';
                ctx.fill();
                
                ctx.beginPath();
                ctx.arc(centerX, centerY, 55, 0, 2 * Math.PI);
                ctx.fillStyle = '#18181e';
                ctx.fill();
                return;
            }

            const filteredData = data.filter(item => item.value > 0);
            let currentAngle = -Math.PI / 2;

            filteredData.forEach(item => {
                const sliceAngle = (item.value / total) * 2 * Math.PI;

                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
                ctx.closePath();
                ctx.fillStyle = item.color;
                ctx.fill();

                ctx.strokeStyle = '#18181e';
                ctx.lineWidth = 3;
                ctx.stroke();

                currentAngle += sliceAngle;
            });

            ctx.beginPath();
            ctx.arc(centerX, centerY, 55, 0, 2 * Math.PI);
            ctx.fillStyle = '#18181e';
            ctx.fill();
        }

        function updateLegend(data, total) {
            const departmentKeys = {
                'Finance': 'finance',
                'RH': 'rh',
                'DAJ': 'daj',
                'Comm': 'comm',
                'SG': 'sg',
                'DSI': 'dsi',
                'DirCab': 'dircab'
            };

            let html = '';
            data.forEach(item => {
                const percentage = total > 0 ? Math.round((item.value / total) * 100) : 0;
                const colorClass = 'color-' + departmentKeys[item.label];
                
                html += `
                    <div class="legend-item">
                        <div class="legend-color ${colorClass}"></div>
                        <div>
                            <strong>${item.label}:</strong> ${item.value} <span class="text-muted small">(${percentage}%)</span>
                        </div>
                    </div>
                `;
            });
            
            $('#statsLegend').html(html);
        }
