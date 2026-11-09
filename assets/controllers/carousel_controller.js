/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import EmblaCarousel from 'embla-carousel';
import { WheelGesturesPlugin } from 'embla-carousel-wheel-gestures';

const prefersInstantScroll = () =>
    document.documentElement.dataset.a11yMode === 'enhanced' ||
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export default class extends Controller {
    static targets = ['viewport', 'previous', 'next', 'dots'];
    static values = { dotLabel: String };

    connect() {
        this.embla = EmblaCarousel(this.viewportTarget, { align: 'start' }, [WheelGesturesPlugin()]);
        this.embla.on('reInit', () => this.renderDots()).on('select', () => this.update());
        this.element.classList.add('is-enhanced');
        this.renderDots();
    }

    disconnect() {
        this.embla.destroy();
        this.element.classList.remove('is-enhanced');
    }

    previous() {
        this.embla.scrollPrev(prefersInstantScroll());
    }

    next() {
        this.embla.scrollNext(prefersInstantScroll());
    }

    goTo(event) {
        this.embla.scrollTo(Number(event.currentTarget.dataset.index), prefersInstantScroll());
    }

    renderDots() {
        const viewportId = this.viewportTarget.id;

        this.dotsTarget.replaceChildren(
            ...this.embla.scrollSnapList().map((_, index) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'carousel__dot';
                dot.dataset.index = String(index);
                dot.dataset.action = 'carousel#goTo';
                dot.dataset.testid = 'carousel-dot';
                dot.setAttribute('aria-label', this.dotLabelValue.replace('%index%', String(index + 1)));
                dot.setAttribute('aria-controls', viewportId);
                return dot;
            }),
        );
        this.update();
    }

    update() {
        const selected = this.embla.selectedScrollSnap();

        this.previousTarget.disabled = !this.embla.canScrollPrev();
        this.nextTarget.disabled = !this.embla.canScrollNext();
        this.dotsTarget.querySelectorAll('button').forEach((dot, index) => {
            if (index === selected) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });
    }
}
