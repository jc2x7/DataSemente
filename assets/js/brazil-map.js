/**
 * Mapa de calor do Brasil por estado (SVG interativo)
 * Cada estado é um path SVG que pode ser colorido conforme dados
 */

const BrazilMap = {
    // Paths SVG dos estados brasileiros (simplificados para performance)
    states: {
        'AC': { name: 'Acre', path: 'M87,bindEvents248 L72,240 65,250 60,265 75,275 95,270 100,255Z' },
        'AL': { name: 'Alagoas', path: 'M478,270 L485,262 495,265 492,275 483,278Z' },
        'AM': { name: 'Amazonas', path: 'M100,160 L85,170 80,190 75,210 70,230 85,245 105,250 130,245 160,240 190,230 200,210 195,190 180,175 160,165 140,160 120,155Z' },
        'AP': { name: 'Amapá', path: 'M260,120 L250,130 245,150 255,165 270,160 280,145 275,125Z' },
        'BA': { name: 'Bahia', path: 'M410,245 L395,240 380,250 370,265 375,285 385,305 400,320 420,325 445,315 460,300 470,280 475,265 465,250 445,240 425,238Z' },
        'CE': { name: 'Ceará', path: 'M450,195 L440,200 435,215 445,230 460,235 475,225 478,210 470,198Z' },
        'DF': { name: 'Distrito Federal', path: 'M355,290 L350,295 355,300 362,295Z' },
        'ES': { name: 'Espírito Santo', path: 'M440,330 L432,325 425,335 430,350 442,348 445,338Z' },
        'GO': { name: 'Goiás', path: 'M330,280 L315,285 310,300 320,320 340,330 360,325 370,310 365,290 350,278Z' },
        'MA': { name: 'Maranhão', path: 'M360,180 L345,185 330,195 325,215 335,235 355,240 375,235 385,220 380,200 370,185Z' },
        'MG': { name: 'Minas Gerais', path: 'M365,310 L350,315 340,330 345,350 360,365 380,370 400,365 415,350 420,330 415,315 400,305 380,300Z' },
        'MS': { name: 'Mato Grosso do Sul', path: 'M270,320 L255,330 250,350 260,370 280,380 300,375 315,360 310,340 295,325Z' },
        'MT': { name: 'Mato Grosso', path: 'M210,230 L195,240 190,260 200,280 220,300 245,310 275,315 300,310 315,290 310,270 295,250 270,235 245,228Z' },
        'PA': { name: 'Pará', path: 'M200,130 L180,140 170,160 175,180 190,200 210,215 235,220 260,215 280,200 290,180 285,160 270,145 250,135 225,128Z' },
        'PB': { name: 'Paraíba', path: 'M465,235 L458,238 455,248 465,252 478,250 485,242 480,235Z' },
        'PE': { name: 'Pernambuco', path: 'M445,248 L435,252 430,262 445,268 465,270 480,262 485,252 475,248 458,245Z' },
        'PI': { name: 'Piauí', path: 'M395,205 L385,210 380,225 385,245 400,255 415,248 420,230 415,215 405,205Z' },
        'PR': { name: 'Paraná', path: 'M295,380 L280,385 270,395 275,410 295,418 315,415 330,405 328,390 315,380Z' },
        'RJ': { name: 'Rio de Janeiro', path: 'M400,370 L390,375 385,385 395,392 410,390 420,382 415,372Z' },
        'RN': { name: 'Rio Grande do Norte', path: 'M470,220 L462,225 460,235 472,238 483,232 485,222Z' },
        'RO': { name: 'Rondônia', path: 'M155,265 L140,270 135,285 145,300 165,305 180,295 182,278 170,268Z' },
        'RR': { name: 'Roraima', path: 'M150,100 L135,110 130,130 140,148 158,150 170,138 172,118 165,105Z' },
        'RS': { name: 'Rio Grande do Sul', path: 'M290,425 L275,430 265,445 270,465 285,478 305,475 318,460 320,440 310,428Z' },
        'SC': { name: 'Santa Catarina', path: 'M305,418 L290,422 285,432 295,440 312,438 320,430 318,420Z' },
        'SE': { name: 'Sergipe', path: 'M475,272 L468,275 470,285 480,285 483,278Z' },
        'SP': { name: 'São Paulo', path: 'M320,360 L305,365 295,375 300,390 315,400 335,398 350,388 355,372 345,360Z' },
        'TO': { name: 'Tocantins', path: 'M330,225 L320,235 315,255 325,275 340,280 355,275 360,255 355,238 345,225Z' },
    },

    // Dimensões do viewBox
    viewBox: '40 80 480 420',

    /**
     * Renderiza o mapa no container especificado
     */
    render(containerId, data, metric = 'total_producao') {
        const container = document.getElementById(containerId);
        if (!container) return;

        // Mapeia dados por UF
        const dataByUf = {};
        let maxVal = 0;
        if (data) {
            data.forEach(d => {
                dataByUf[d.uf] = d;
                const val = parseFloat(d[metric]) || 0;
                if (val > maxVal) maxVal = val;
            });
        }

        // Gera SVG
        let svg = `<svg viewBox="${this.viewBox}" xmlns="http://www.w3.org/2000/svg" class="brazil-svg">`;

        // Renderiza cada estado
        Object.entries(this.states).forEach(([uf, info]) => {
            const stateData = dataByUf[uf];
            const val = stateData ? (parseFloat(stateData[metric]) || 0) : 0;
            const intensity = maxVal > 0 ? val / maxVal : 0;
            const color = this.getColor(intensity);

            svg += `<path d="${info.path}"
                          fill="${color}"
                          stroke="#ffffff"
                          stroke-width="1.5"
                          data-uf="${uf}"
                          data-name="${info.name}"
                          data-value="${val}"
                          class="state-path">
                        <title>${info.name} (${uf})</title>
                    </path>`;

            // Label do estado
            const center = this.getPathCenter(info.path);
            svg += `<text x="${center.x}" y="${center.y}"
                          text-anchor="middle"
                          dominant-baseline="central"
                          class="state-label"
                          font-size="7"
                          fill="#333"
                          font-weight="bold"
                          pointer-events="none">${uf}</text>`;
        });

        svg += '</svg>';

        // Legenda
        let legend = '<div class="map-legend">';
        legend += '<span class="legend-label">Menor</span>';
        legend += '<div class="legend-gradient"></div>';
        legend += '<span class="legend-label">Maior</span>';
        legend += '</div>';

        container.innerHTML = svg + legend;

        // Eventos de hover/click
        container.querySelectorAll('.state-path').forEach(path => {
            path.addEventListener('mouseenter', (e) => this.showTooltip(e, dataByUf, metric));
            path.addEventListener('mouseleave', () => this.hideTooltip());
            path.addEventListener('click', (e) => {
                const uf = e.target.dataset.uf;
                if (typeof this.onStateClick === 'function') {
                    this.onStateClick(uf);
                }
            });
        });
    },

    getColor(intensity) {
        if (intensity === 0) return '#e8e8e8';
        // Gradiente de verde claro a verde escuro
        const r = Math.round(200 - intensity * 170);
        const g = Math.round(230 - intensity * 80);
        const b = Math.round(200 - intensity * 170);
        return `rgb(${r},${g},${b})`;
    },

    getPathCenter(pathStr) {
        const nums = pathStr.match(/[\d.]+/g);
        if (!nums || nums.length < 4) return { x: 0, y: 0 };
        let sumX = 0, sumY = 0, count = 0;
        for (let i = 0; i < nums.length - 1; i += 2) {
            sumX += parseFloat(nums[i]);
            sumY += parseFloat(nums[i + 1]);
            count++;
        }
        return { x: sumX / count, y: sumY / count };
    },

    showTooltip(e, dataByUf, metric) {
        const uf = e.target.dataset.uf;
        const name = e.target.dataset.name;
        const data = dataByUf[uf];

        let tooltip = document.getElementById('map-tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'map-tooltip';
            tooltip.className = 'map-tooltip';
            document.body.appendChild(tooltip);
        }

        let html = `<strong>${name} (${uf})</strong>`;
        if (data) {
            html += `<br>Registros: ${Number(data.registros).toLocaleString('pt-BR')}`;
            html += `<br>Área: ${Number(data.total_area).toLocaleString('pt-BR', {minimumFractionDigits: 2})} ha`;
            html += `<br>Prod. Estimada: ${Number(data.total_producao).toLocaleString('pt-BR', {minimumFractionDigits: 2})} t`;
            html += `<br>Cultivares: ${data.total_cultivares}`;
            html += `<br>Municípios: ${data.total_municipios}`;
        } else {
            html += '<br><em>Sem dados</em>';
        }

        tooltip.innerHTML = html;
        tooltip.style.display = 'block';
        tooltip.style.left = (e.pageX + 15) + 'px';
        tooltip.style.top = (e.pageY - 10) + 'px';

        e.target.style.opacity = '0.8';
        e.target.style.strokeWidth = '2.5';
    },

    hideTooltip() {
        const tooltip = document.getElementById('map-tooltip');
        if (tooltip) tooltip.style.display = 'none';

        document.querySelectorAll('.state-path').forEach(p => {
            p.style.opacity = '1';
            p.style.strokeWidth = '1.5';
        });
    },

    onStateClick: null,
};
