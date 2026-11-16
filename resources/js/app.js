// Page behaviour. Each module talks to the markup through data attributes rather
// than class names, so restyling a section never breaks its behaviour.
//
// The marketing page loads the header, the program tabs and back-to-top; the exam
// room only has work for `initExam`; the result page adds `initReview`, the
// analysis panel's in-flight state and the chat composer; the item thread page
// only has the composer. Every module returns immediately when its root element is
// absent, so one bundle serves them all.
import { initHeader } from './modules/header.js';
import { initProgramTabs } from './modules/program-tabs.js';
import { initBackToTop } from './modules/back-to-top.js';
import { initExam } from './modules/exam.js';
import { initReview } from './modules/review.js';
import { initResultAnalysis } from './modules/result-analysis.js';
import { initChat } from './modules/chat.js';

initHeader();
initProgramTabs();
initBackToTop();
initExam();
initReview();
initResultAnalysis();
initChat();
