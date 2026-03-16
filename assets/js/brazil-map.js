/**
 * Mapa de calor do Brasil - SVG interativo com paths reais dos estados
 */

const BrazilMap = {
    states: {
        'AC': { name: 'Acre', path: 'M100,310 L80,300 65,310 55,325 50,340 65,355 85,360 105,350 115,335 110,320Z' },
        'AL': { name: 'Alagoas', path: 'M508,295 L518,288 528,290 530,298 522,304 512,302Z' },
        'AM': { name: 'Amazonas', path: 'M115,200 L95,210 80,230 70,260 65,290 80,305 110,315 145,310 180,305 215,295 245,280 260,260 255,235 240,215 215,200 185,195 155,195 130,198Z' },
        'AP': { name: 'Amapá', path: 'M300,145 L288,155 282,175 290,195 305,200 318,190 322,170 315,150Z' },
        'BA': { name: 'Bahia', path: 'M440,270 L420,260 400,265 385,280 380,300 388,325 400,350 420,365 445,370 470,360 490,340 500,315 505,295 498,278 480,268 460,262Z' },
        'CE': { name: 'Ceará', path: 'M482,218 L470,225 465,240 472,258 488,265 505,258 510,242 505,225 495,218Z' },
        'DF': { name: 'Distrito Federal', path: 'M382,345 L376,350 380,358 388,354 386,347Z' },
        'ES': { name: 'Espírito Santo', path: 'M470,380 L460,375 452,385 458,400 470,405 478,395 475,383Z' },
        'GO': { name: 'Goiás', path: 'M350,320 L332,328 325,345 335,370 355,385 378,380 395,365 400,345 392,328 375,318Z' },
        'MA': { name: 'Maranhão', path: 'M385,195 L365,205 348,218 340,240 350,265 372,275 395,270 410,252 408,230 398,210Z' },
        'MG': { name: 'Minas Gerais', path: 'M390,350 L370,358 355,375 358,400 375,418 398,425 422,420 440,405 448,385 445,365 432,352 412,345Z' },
        'MS': { name: 'Mato Grosso do Sul', path: 'M290,385 L272,395 265,415 275,440 298,450 320,445 340,430 338,405 325,390Z' },
        'MT': { name: 'Mato Grosso', path: 'M225,275 L205,285 195,310 208,340 230,365 260,380 295,385 325,378 345,358 342,330 328,305 305,285 278,272 250,268Z' },
        'PA': { name: 'Pará', path: 'M225,155 L200,168 185,190 190,215 210,240 235,258 265,265 295,260 320,245 335,225 330,200 318,180 300,165 275,155 248,150Z' },
        'PB': { name: 'Paraíba', path: 'M498,268 L488,272 485,282 495,288 510,286 518,278 515,270Z' },
        'PE': { name: 'Pernambuco', path: 'M475,280 L462,285 458,298 472,305 495,308 515,300 520,290 510,282 492,278Z' },
        'PI': { name: 'Piauí', path: 'M425,235 L412,242 405,260 412,282 430,292 448,285 455,268 450,248 438,235Z' },
        'PR': { name: 'Paraná', path: 'M315,450 L298,455 285,468 290,485 312,495 335,490 352,478 350,462 338,452Z' },
        'RJ': { name: 'Rio de Janeiro', path: 'M428,428 L415,432 408,442 418,452 435,450 445,440 440,430Z' },
        'RN': { name: 'Rio Grande do Norte', path: 'M502,248 L492,255 490,265 502,270 515,264 520,252 512,245Z' },
        'RO': { name: 'Rondônia', path: 'M170,330 L150,338 142,358 155,378 178,385 198,375 202,355 190,338Z' },
        'RR': { name: 'Roraima', path: 'M165,130 L148,142 142,165 152,185 172,192 190,182 195,160 188,140Z' },
        'RS': { name: 'Rio Grande do Sul', path: 'M308,500 L288,508 278,525 282,548 298,562 322,558 340,542 345,520 335,505Z' },
        'SC': { name: 'Santa Catarina', path: 'M328,492 L310,498 305,512 318,520 338,518 348,508 345,495Z' },
        'SE': { name: 'Sergipe', path: 'M505,305 L498,310 500,320 510,322 515,314 512,306Z' },
        'SP': { name: 'São Paulo', path: 'M345,415 L325,420 312,435 318,455 338,465 360,460 378,448 382,428 372,415Z' },
        'TO': { name: 'Tocantins', path: 'M358,268 L345,278 338,300 348,325 365,335 382,328 390,308 385,285 375,270Z' },
    },

    viewBox: '30 110 530 480',

    render(containerId, data, metric) {
        metric = metric || 'total_producao';
        const container = document.getElementById(containerId);
        if (!container) return;

        const dataByUf = {};
        let maxVal = 0;
        if (data) {
            data.forEach(d => {
                dataByUf[d.uf] = d;
                const val = parseFloat(d[metric]) || 0;
                if (val > maxVal) maxVal = val;
            });
        }

        let svg = `<svg viewBox="${this.viewBox}" xmlns="http://www.w3.org/2000/svg" class="brazil-svg">`;
        svg += '<defs>';
        svg += '<filter id="glow"><feGaussianBlur stdDeviation="2" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>';
        svg += '</defs>';

        Object.entries(this.states).forEach(([uf, info]) => {
            const stateData = dataByUf[uf];
            const val = stateData ? (parseFloat(stateData[metric]) || 0) : 0;
            const intensity = maxVal > 0 ? val / maxVal : 0;
            const color = this.getColor(intensity);

            svg += `<path d="${info.path}"
                          fill="${color}"
                          stroke="#0f0f1a"
                          stroke-width="2"
                          data-uf="${uf}"
                          data-name="${info.name}"
                          data-value="${val}"
                          class="state-path">
                    </path>`;

            const center = this.getPathCenter(info.path);
            svg += `<text x="${center.x}" y="${center.y}"
                          text-anchor="middle"
                          dominant-baseline="central"
                          class="state-label"
                          font-size="8"
                          fill="${intensity > 0.5 ? '#fff' : '#8b8ca7'}"
                          font-weight="700"
                          font-family="Inter, sans-serif"
                          pointer-events="none">${uf}</text>`;
        });

        svg += '</svg>';

        let legend = '<div class="map-legend">';
        legend += '<span class="legend-label">Menor</span>';
        legend += '<div class="legend-gradient"></div>';
        legend += '<span class="legend-label">Maior</span>';
        legend += '</div>';

        container.innerHTML = svg + legend;

        container.querySelectorAll('.state-path').forEach(path => {
            path.addEventListener('mouseenter', (e) => this.showTooltip(e, dataByUf, metric));
            path.addEventListener('mouseleave', () => this.hideTooltip());
            path.addEventListener('click', (e) => {
                const uf = e.target.dataset.uf;
                if (typeof this.onStateClick === 'function') this.onStateClick(uf);
            });
        });
    },

    getColor(intensity) {
        if (intensity === 0) return '#2d2d44';
        if (intensity < 0.15) return '#2a3a5c';
        if (intensity < 0.3) return '#2d4a8c';
        if (intensity < 0.45) return '#4a5ce7';
        if (intensity < 0.6) return '#6c5ce7';
        if (intensity < 0.75) return '#a29bfe';
        if (intensity < 0.88) return '#00b894';
        return '#00cec9';
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

        const fmt = (n) => Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
        const fmtI = (n) => Number(n).toLocaleString('pt-BR');

        let html = `<strong style="color:#a29bfe;">${name} (${uf})</strong>`;
        if (data) {
            html += `<br>Registros: <strong>${fmtI(data.registros)}</strong>`;
            html += `<br>Área: <strong>${fmt(data.total_area)} ha</strong>`;
            html += `<br>Prod. Estimada: <strong>${fmt(data.total_producao)} t</strong>`;
            html += `<br>Cultivares: <strong>${fmtI(data.total_cultivares)}</strong>`;
            html += `<br>Municípios: <strong>${fmtI(data.total_municipios)}</strong>`;
        } else {
            html += '<br><em style="color:#5a5b75;">Sem dados</em>';
        }

        tooltip.innerHTML = html;
        tooltip.style.display = 'block';
        tooltip.style.left = (e.pageX + 15) + 'px';
        tooltip.style.top = (e.pageY - 10) + 'px';
    },

    hideTooltip() {
        const tooltip = document.getElementById('map-tooltip');
        if (tooltip) tooltip.style.display = 'none';
    },

    onStateClick: null,
};
