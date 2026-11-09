import type { Locale } from "@/lib/locale";

import { AnatomySection } from "./AnatomySection";
import { CinematicSequence } from "./CinematicSequence";
import { ContactSection } from "./ContactSection";
import { DigitalImplant } from "./DigitalImplant";
import { DoctorProfileSection } from "./DoctorProfileSection";
import { HeroSection } from "./HeroSection";
import { HomeSlot } from "./HomeSlot";
import { IntroductionSection } from "./IntroductionSection";
import { JournalSection } from "./JournalSection";
import { ReviewsSection } from "./ReviewsSection";
import { TreatmentsSection } from "./TreatmentsSection";

export type HomePageProps = {
  locale: Locale;
};

export function HomePage({ locale }: HomePageProps) {
  return (
    <div className="flex flex-col">
      <HomeSlot id="hero">
        <HeroSection locale={locale} />
      </HomeSlot>
      <HomeSlot id="intro">
        <IntroductionSection locale={locale} />
      </HomeSlot>
      <HomeSlot id="treatments">
        <TreatmentsSection locale={locale} limit={6} />
      </HomeSlot>
      <HomeSlot id="implant">
        <DigitalImplant />
      </HomeSlot>
      <HomeSlot id="surgeon">
        <DoctorProfileSection locale={locale} compact />
      </HomeSlot>
      <HomeSlot id="reviews">
        <ReviewsSection locale={locale} />
      </HomeSlot>
      <HomeSlot id="journal">
        <JournalSection locale={locale} />
      </HomeSlot>
      <HomeSlot id="story">
        <CinematicSequence locale={locale} />
      </HomeSlot>
      <HomeSlot id="anatomy">
        <AnatomySection locale={locale} />
      </HomeSlot>
      <HomeSlot id="contact">
        <ContactSection locale={locale} heading place="home" />
      </HomeSlot>
    </div>
  );
}
