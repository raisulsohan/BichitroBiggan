<?php
/**
 * Template Name: SEO Demo Standalone
 *
 * Standalone, full-width, zero-header presentation template for /seo-demo.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="bn" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Smart Publishing & SEO Engine — Interactive Live Demo</title>
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', 'Hind Siliguri', sans-serif;
    }
    .font-mono {
      font-family: 'JetBrains Mono', monospace;
    }
    @keyframes pulse-glow {
      0%, 100% { opacity: 0.4; transform: scale(1); }
      50% { opacity: 0.8; transform: scale(1.03); }
    }
    @keyframes scan {
      0% { top: 0%; opacity: 0; }
      50% { opacity: 0.8; }
      100% { top: 100%; opacity: 0; }
    }
    @keyframes flow-line {
      0% { background-position: 0% 50%; }
      100% { background-position: 200% 50%; }
    }
    .flow-active {
      background: linear-gradient(90deg, #06b6d4, #a855f7, #10b981, #06b6d4);
      background-size: 200% 100%;
      animation: flow-line 2s linear infinite;
    }
    .glow-cyan {
      box-shadow: 0 0 40px -5px rgba(6, 182, 212, 0.35);
    }
    .glow-emerald {
      box-shadow: 0 0 40px -5px rgba(16, 185, 129, 0.35);
    }
    .score-circle {
      transition: stroke-dashoffset 1.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
  </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 md:p-8 antialiased flex flex-col items-center justify-center relative overflow-x-hidden selection:bg-cyan-500/30">

  <!-- Ambient Glow Background -->
  <div class="fixed top-0 left-1/4 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none -z-10 animate-pulse"></div>
  <div class="fixed bottom-10 right-1/4 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl pointer-events-none -z-10 animate-pulse" style="animation-delay: 2s;"></div>

  <!-- Main Presentation Container -->
  <div class="w-full max-w-4xl bg-slate-900/90 border border-slate-800 rounded-3xl p-6 md:p-10 shadow-2xl backdrop-blur-2xl relative overflow-hidden my-auto">
    
    <!-- Top Action Bar with Live Demo Trigger -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-6 mb-8">
      <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-xs font-semibold uppercase tracking-wider mb-2">
          <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
          Live Architecture Demo
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight flex items-center gap-3">
          AI Smart Publishing Engine
        </h1>
        <p class="text-slate-400 text-sm mt-1">
          কাঁচা টেক্সট ও ফিচার ইমেজ থেকে পূর্ণাঙ্গ এসইও অপ্টিমাইজড লাইভ প্রকাশনা
        </p>
      </div>

      <!-- Controls -->
      <div class="flex items-center gap-3">
        <button id="runDemoBtn" onclick="runSimulation()" class="group relative inline-flex items-center gap-2.5 px-6 py-3 rounded-xl bg-gradient-to-r from-cyan-500 via-teal-500 to-emerald-500 text-slate-950 font-bold text-sm tracking-wide shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 cursor-pointer">
          <svg id="playIcon" class="w-4 h-4 transition-transform group-hover:scale-110" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          <span id="btnText">অ্যানিমেশন ডেমো চালান ▶</span>
        </button>
        <button onclick="resetSimulation()" title="রিসেট" class="p-3 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 border border-slate-700/60 transition-colors cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        </button>
      </div>
    </div>

    <!-- Interactive Progress Step Bar -->
    <div class="relative mb-8">
      <div class="h-1.5 w-full bg-slate-800 rounded-full overflow-hidden">
        <div id="pipelineProgress" class="h-full w-0 bg-gradient-to-r from-cyan-500 via-purple-500 to-emerald-500 transition-all duration-700 rounded-full"></div>
      </div>
      
      <div class="flex justify-between -mt-3.5 px-1">
        <div id="stepNode1" class="step-node flex flex-col items-center group cursor-pointer transition-all duration-300">
          <div class="w-6 h-6 rounded-full bg-slate-900 border-2 border-slate-700 text-[10px] font-bold text-slate-400 flex items-center justify-center transition-all duration-300">1</div>
          <span class="text-[11px] font-semibold text-slate-400 mt-2 transition-colors">ইনপুট ও ছবি</span>
        </div>
        <div id="stepNode2" class="step-node flex flex-col items-center group cursor-pointer transition-all duration-300">
          <div class="w-6 h-6 rounded-full bg-slate-900 border-2 border-slate-700 text-[10px] font-bold text-slate-400 flex items-center justify-center transition-all duration-300">2</div>
          <span class="text-[11px] font-semibold text-slate-400 mt-2 transition-colors">AI অ্যানালাইসিস</span>
        </div>
        <div id="stepNode3" class="step-node flex flex-col items-center group cursor-pointer transition-all duration-300">
          <div class="w-6 h-6 rounded-full bg-slate-900 border-2 border-slate-700 text-[10px] font-bold text-slate-400 flex items-center justify-center transition-all duration-300">3</div>
          <span class="text-[11px] font-semibold text-slate-400 mt-2 transition-colors">অন-পেজ এসইও</span>
        </div>
        <div id="stepNode4" class="step-node flex flex-col items-center group cursor-pointer transition-all duration-300">
          <div class="w-6 h-6 rounded-full bg-slate-900 border-2 border-slate-700 text-[10px] font-bold text-slate-400 flex items-center justify-center transition-all duration-300">4</div>
          <span class="text-[11px] font-semibold text-slate-400 mt-2 transition-colors">ফিচার্ড ইমেজ সহ লাইভ</span>
        </div>
      </div>
    </div>

    <!-- Animated Pipeline Visual Stage -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-8">
      
      <!-- Left Column: Step 1 (Input & Raw Image Card) -->
      <div id="cardStage1" class="lg:col-span-4 bg-slate-950/60 border border-slate-800 rounded-2xl p-5 transition-all duration-500 relative overflow-hidden">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">ধাপ ০১ : ইনপুট সংগ্রহ</span>
          <span id="badge1" class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">অপেক্ষমাণ</span>
        </div>
        
        <div class="space-y-3">
          <!-- Text Preview -->
          <div class="p-3 bg-slate-900/90 rounded-xl border border-slate-800/80 relative">
            <div id="scanEffect" class="hidden absolute left-0 right-0 h-0.5 bg-cyan-400/80 blur-[1px] animate-[scan_1.5s_infinite]"></div>
            <div class="text-[11px] text-slate-400 font-medium line-clamp-2" id="inputText">
              "জাপানে শতবর্ষী মানুষের সংখ্যা প্রথমবারের মতো এক লক্ষ ছাড়িয়েছে... জাপানিরা এত দীর্ঘজীবী কেন?..."
            </div>
          </div>
          
          <!-- Image Ingest Box with Real Thumbnail -->
          <div id="imageBox" class="p-3 bg-slate-900/90 rounded-xl border border-slate-800/80 flex items-center gap-3 transition-all duration-500">
            <div class="w-12 h-12 rounded-lg bg-slate-800 border border-slate-700 overflow-hidden relative shrink-0">
              <img id="inputImgThumb" src="https://bichitrobiggan.com/wp-content/uploads/2026/09/why-japanese-live-longer-longevity-secrets.webp" alt="Input Thumbnail" class="w-full h-full object-cover grayscale opacity-50 transition-all duration-500">
            </div>
            <div class="overflow-hidden">
              <div class="text-xs font-semibold text-slate-300 truncate" id="imageTitle">ক্লিপবোর্ড ছবি</div>
              <div class="text-[10px] text-slate-500" id="imageStatus">স্বয়ংক্রিয় সেভ হবে</div>
            </div>
          </div>
        </div>

        <div class="mt-4 pt-3 border-t border-slate-800/60 flex items-center justify-between text-[11px] text-slate-400">
          <span>টাইপ ও আপলোড:</span>
          <span class="font-mono text-cyan-400 font-bold">০ সেকেন্ড (অটো)</span>
        </div>
      </div>

      <!-- Center Column: Step 2 & 3 (AI Core & Realtime SEO Gauge) -->
      <div id="cardStage2" class="lg:col-span-5 bg-slate-950/60 border border-slate-800 rounded-2xl p-5 transition-all duration-500 relative">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">ধাপ ০২ ও ০৩ : এআই ও এসইও</span>
          <span id="badge2" class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">অপেক্ষমাণ</span>
        </div>

        <!-- Gauge & Main Meta -->
        <div class="flex items-center gap-4 mb-4 bg-slate-900/80 p-3.5 rounded-xl border border-slate-800/80">
          <!-- Circular Gauge -->
          <div class="relative w-16 h-16 shrink-0 flex items-center justify-center">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 36 36">
              <path class="text-slate-800" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
              <path id="scoreCircle" class="score-circle text-emerald-400" stroke-dasharray="100, 100" stroke-dashoffset="100" stroke-width="3" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
              <span id="scoreText" class="text-sm font-black text-white font-mono leading-none">০%</span>
              <span class="text-[7px] text-slate-400 uppercase tracking-tight mt-0.5">SEO Score</span>
            </div>
          </div>
          <div class="overflow-hidden space-y-1">
            <div class="text-[10px] uppercase font-bold text-slate-500">Focus Keyphrase</div>
            <div id="keyphraseDisplay" class="text-xs font-bold text-slate-300 font-mono truncate">—</div>
            <div id="slugDisplay" class="text-[10px] text-cyan-400 font-mono truncate">—</div>
          </div>
        </div>

        <!-- Live Ticking Character Counts -->
        <div class="space-y-2 text-xs">
          <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800">
            <div class="flex justify-between items-center text-[10px] mb-1">
              <span class="text-slate-400 font-medium">SEO Title (২৫–৬০ অক্ষর)</span>
              <span id="titleCount" class="font-mono text-slate-500">০ অক্ষর</span>
            </div>
            <div id="titleText" class="text-[11px] text-slate-300 font-medium truncate">অপেক্ষমাণ...</div>
          </div>

          <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800">
            <div class="flex justify-between items-center text-[10px] mb-1">
              <span class="text-slate-400 font-medium">Meta Description (১২০–১৫৫ অক্ষর)</span>
              <span id="descCount" class="font-mono text-slate-500">০ অক্ষর</span>
            </div>
            <div id="descText" class="text-[11px] text-slate-400 line-clamp-1">অপেক্ষমাণ...</div>
          </div>

          <!-- Image Alt Tag Output -->
          <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800">
            <div class="flex justify-between items-center text-[10px] mb-1">
              <span class="text-slate-400 font-medium">Image Alt Text (SEO Friendly)</span>
              <span id="altBadge" class="text-[9px] font-mono text-slate-500">অটো জেনারেটেড</span>
            </div>
            <div id="altText" class="text-[10px] text-slate-400 truncate">অপেক্ষমাণ...</div>
          </div>
        </div>
      </div>

      <!-- Right Column: Step 4 (Live WordPress Card with Real Featured Image) -->
      <div id="cardStage3" class="lg:col-span-3 bg-slate-950/60 border border-slate-800 rounded-2xl p-5 transition-all duration-500 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-3">
            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">ধাপ ০৪ : সাইটে প্রকাশ</span>
            <span id="badge3" class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">ড্রাফট</span>
          </div>

          <!-- Mini Smartphone Card Simulation with Real Featured Image -->
          <div id="phoneMockup" class="bg-slate-900 border border-slate-800 rounded-xl p-3 text-center transition-all duration-500 mb-3 overflow-hidden group">
            
            <!-- Real Featured Image Container -->
            <div class="w-full h-28 rounded-lg bg-slate-800 mb-2.5 overflow-hidden relative shadow-inner">
              <img id="mockupHeroImage" src="https://bichitrobiggan.com/wp-content/uploads/2026/09/why-japanese-live-longer-longevity-secrets.webp" alt="Featured Image" class="w-full h-full object-cover scale-105 opacity-40 blur-[1px] transition-all duration-700">
              
              <div id="liveBadge" class="hidden absolute top-2 right-2 px-2 py-0.5 rounded-full bg-emerald-500 text-slate-950 text-[9px] font-bold uppercase tracking-wider shadow-lg flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-950 animate-ping"></span>
                Live
              </div>
              
              <div id="featuredTag" class="hidden absolute bottom-2 left-2 px-2 py-0.5 rounded-md bg-slate-950/80 backdrop-blur-md text-[9px] font-bold text-cyan-300 border border-cyan-500/30">
                ★ Featured Media #2907
              </div>
            </div>

            <div id="mockupTitle" class="text-[11px] font-bold text-slate-300 line-clamp-1">জাপানিরা এত দীর্ঘজীবী কেন?</div>
            <div id="mockupMeta" class="text-[9px] text-slate-500 mt-1 flex items-center justify-center gap-2">
              <span>জীবনের বিজ্ঞান</span>
              <span>•</span>
              <span>তানভীর হোসেন</span>
            </div>
          </div>
        </div>

        <div id="statusReport" class="p-2.5 rounded-xl bg-slate-900/50 border border-slate-800/80 text-[11px] text-slate-400 text-center font-mono">
          স্ট্যাটাস: রেডি
        </div>
      </div>

    </div>

    <!-- Impact & Efficiency Counters -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
      <div class="bg-slate-950/60 border border-slate-800 p-3.5 rounded-xl hover:border-slate-700 transition-colors">
        <div id="statSpeed" class="text-2xl font-black text-cyan-400 font-mono">১৫ মিনিট</div>
        <div class="text-slate-400 text-[11px] mt-0.5 font-medium">পাবলিশের সময়</div>
      </div>
      <div class="bg-slate-950/60 border border-slate-800 p-3.5 rounded-xl hover:border-slate-700 transition-colors">
        <div id="statSeo" class="text-2xl font-black text-emerald-400 font-mono">ম্যানুয়াল</div>
        <div class="text-slate-400 text-[11px] mt-0.5 font-medium">On-Page এসইও</div>
      </div>
      <div class="bg-slate-950/60 border border-slate-800 p-3.5 rounded-xl hover:border-slate-700 transition-colors">
        <div class="text-2xl font-black text-purple-400 font-mono">০ প্লাগইন</div>
        <div class="text-slate-400 text-[11px] mt-0.5 font-medium">লাইটওয়েট থিম মেটা</div>
      </div>
      <div class="bg-slate-950/60 border border-slate-800 p-3.5 rounded-xl hover:border-slate-700 transition-colors">
        <div id="statApproval" class="text-2xl font-black text-amber-400 font-mono">নিরাপদ</div>
        <div class="text-slate-400 text-[11px] mt-0.5 font-medium">হিউম্যান অ্যাপ্রুভাল</div>
      </div>
    </div>

  </div>

  <script>
    let isRunning = false;
    let animTimeout;

    const data = {
      keyphrase: 'জাপানিরা এত দীর্ঘজীবী কেন',
      slug: 'why-japanese-live-longer-longevity-secrets',
      title: 'জাপানিরা এত দীর্ঘজীবী কেন: শতবর্ষী মানুষের গোপন রহস্য',
      titleChars: 53,
      desc: 'জাপানিরা এত দীর্ঘজীবী কেন? স্বাস্থ্যকর খাদ্যাভ্যাস, হারা হাচি বু, ইকিগাই ও সক্রিয় জীবনযাপনের মাধ্যমে শতবর্ষী মানুষদের দীর্ঘ জীবনের রহস্য জানুন।',
      descChars: 152,
      alt: 'জাপানিরা এত দীর্ঘজীবী কেন তার পেছনে স্বাস্থ্যকর খাবার ও সক্রিয় জীবনযাপনের প্রতীকী চিত্র'
    };

    function resetSimulation() {
      clearTimeout(animTimeout);
      isRunning = false;
      document.getElementById('btnText').innerText = 'অ্যানিমেশন ডেমো চালান ▶';
      document.getElementById('runDemoBtn').classList.remove('opacity-50', 'pointer-events-none');

      // Progress bar
      document.getElementById('pipelineProgress').style.width = '0%';
      document.querySelectorAll('.step-node div').forEach(node => {
        node.className = 'w-6 h-6 rounded-full bg-slate-900 border-2 border-slate-700 text-[10px] font-bold text-slate-400 flex items-center justify-center transition-all duration-300';
      });

      // Stage 1
      document.getElementById('cardStage1').className = 'lg:col-span-4 bg-slate-950/60 border border-slate-800 rounded-2xl p-5 transition-all duration-500 relative overflow-hidden';
      document.getElementById('badge1').innerText = 'অপেক্ষমাণ';
      document.getElementById('badge1').className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700';
      document.getElementById('scanEffect').classList.add('hidden');
      document.getElementById('inputImgThumb').className = 'w-full h-full object-cover grayscale opacity-50 transition-all duration-500';
      document.getElementById('imageStatus').innerText = 'স্বয়ংক্রিয় সেভ হবে';

      // Stage 2
      document.getElementById('cardStage2').className = 'lg:col-span-5 bg-slate-950/60 border border-slate-800 rounded-2xl p-5 transition-all duration-500 relative';
      document.getElementById('badge2').innerText = 'অপেক্ষমাণ';
      document.getElementById('badge2').className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700';
      document.getElementById('scoreCircle').style.strokeDashoffset = '100';
      document.getElementById('scoreText').innerText = '০%';
      document.getElementById('keyphraseDisplay').innerText = '—';
      document.getElementById('slugDisplay').innerText = '—';
      document.getElementById('titleCount').innerText = '০ অক্ষর';
      document.getElementById('titleText').innerText = 'অপেক্ষমাণ...';
      document.getElementById('descCount').innerText = '০ অক্ষর';
      document.getElementById('descText').innerText = 'অপেক্ষমাণ...';
      document.getElementById('altText').innerText = 'অপেক্ষমাণ...';

      // Stage 3
      document.getElementById('cardStage3').className = 'lg:col-span-3 bg-slate-950/60 border border-slate-800 rounded-2xl p-5 transition-all duration-500 flex flex-col justify-between';
      document.getElementById('badge3').innerText = 'ড্রাফট';
      document.getElementById('badge3').className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700';
      document.getElementById('mockupHeroImage').className = 'w-full h-full object-cover scale-105 opacity-40 blur-[1px] transition-all duration-700';
      document.getElementById('liveBadge').classList.add('hidden');
      document.getElementById('featuredTag').classList.add('hidden');
      document.getElementById('statusReport').innerText = 'স্ট্যাটাস: রেডি';
      document.getElementById('statusReport').className = 'p-2.5 rounded-xl bg-slate-900/50 border border-slate-800/80 text-[11px] text-slate-400 text-center font-mono';

      // Stats
      document.getElementById('statSpeed').innerText = '১৫ মিনিট';
      document.getElementById('statSpeed').className = 'text-2xl font-black text-cyan-400 font-mono';
      document.getElementById('statSeo').innerText = 'ম্যানুয়াল';
    }

    function runSimulation() {
      if (isRunning) return;
      resetSimulation();
      isRunning = true;

      const btn = document.getElementById('runDemoBtn');
      btn.classList.add('opacity-50', 'pointer-events-none');
      document.getElementById('btnText').innerText = 'প্রসেসিং হচ্ছে...';

      // STEP 1: Input & Image Captured (0s - 1.2s)
      document.getElementById('pipelineProgress').style.width = '25%';
      setNodeActive('stepNode1');
      document.getElementById('cardStage1').className = 'lg:col-span-4 bg-slate-950/90 border border-cyan-500/50 rounded-2xl p-5 glow-cyan transition-all duration-500 relative overflow-hidden';
      document.getElementById('badge1').innerText = 'ক্যাপচার্ড ✅';
      document.getElementById('badge1').className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-cyan-950 text-cyan-400 border border-cyan-800';
      document.getElementById('scanEffect').classList.remove('hidden');
      document.getElementById('inputImgThumb').className = 'w-full h-full object-cover grayscale-0 opacity-100 scale-105 transition-all duration-500';
      document.getElementById('imageStatus').innerText = 'ক্লিপবোর্ড থেকে সংরক্ষিত';

      // STEP 2: AI Parsing & Keyphrase (1.5s - 3.5s)
      animTimeout = setTimeout(() => {
        document.getElementById('pipelineProgress').style.width = '55%';
        setNodeActive('stepNode2');
        document.getElementById('cardStage2').className = 'lg:col-span-5 bg-slate-950/90 border border-purple-500/50 rounded-2xl p-5 transition-all duration-500 relative';
        document.getElementById('badge2').innerText = 'বিশ্লেষণ...';
        document.getElementById('badge2').className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-purple-950 text-purple-400 border border-purple-800';

        document.getElementById('keyphraseDisplay').innerText = data.keyphrase;
        document.getElementById('slugDisplay').innerText = '/' + data.slug + '/';

        animateText('titleText', data.title);
        animateNumber('titleCount', 0, data.titleChars, ' অক্ষর');

      }, 1400);

      // STEP 3: SEO Optimization & Gauge (3.5s - 5.5s)
      animTimeout = setTimeout(() => {
        document.getElementById('pipelineProgress').style.width = '80%';
        setNodeActive('stepNode3');
        document.getElementById('cardStage2').className = 'lg:col-span-5 bg-slate-950/90 border border-emerald-500/50 rounded-2xl p-5 glow-emerald transition-all duration-500 relative';
        document.getElementById('badge2').innerText = '১০০% এসইও রেডি ✅';
        document.getElementById('badge2').className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-400 border border-emerald-800';

        animateText('descText', data.desc);
        animateNumber('descCount', 0, data.descChars, ' অক্ষর');

        animateText('altText', data.alt);

        // Circle Gauge Fill to 100%
        document.getElementById('scoreCircle').style.strokeDashoffset = '0';
        animateNumber('scoreText', 0, 100, '%');

        document.getElementById('statSeo').innerText = '১০০% অটো';

      }, 3400);

      // STEP 4: Live Publishing with Real Featured Image (5.5s - 7.0s)
      animTimeout = setTimeout(() => {
        document.getElementById('pipelineProgress').style.width = '100%';
        setNodeActive('stepNode4');
        document.getElementById('cardStage3').className = 'lg:col-span-3 bg-slate-950/90 border border-emerald-500/60 rounded-2xl p-5 glow-emerald transition-all duration-500 flex flex-col justify-between';
        document.getElementById('badge3').innerText = 'লাইভ ✅';
        document.getElementById('badge3').className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-emerald-500 text-slate-950 font-bold border border-emerald-400';
        
        // Reveal Featured Hero Image in full glory
        document.getElementById('mockupHeroImage').className = 'w-full h-full object-cover scale-100 opacity-100 blur-0 transition-all duration-700 shadow-lg';
        document.getElementById('liveBadge').classList.remove('hidden');
        document.getElementById('featuredTag').classList.remove('hidden');
        
        document.getElementById('statusReport').innerHTML = '<span class="text-emerald-400 font-bold">✓ সাইটে লাইভ প্রকাশিত</span>';
        document.getElementById('statusReport').className = 'p-2.5 rounded-xl bg-emerald-950/40 border border-emerald-800 text-[11px] text-center font-mono';

        // Animate Speed: 15 min -> 30 sec
        document.getElementById('statSpeed').innerText = '৩০ সেকেন্ড';
        document.getElementById('statSpeed').className = 'text-2xl font-black text-emerald-400 font-mono animate-bounce';

        btn.classList.remove('opacity-50', 'pointer-events-none');
        document.getElementById('btnText').innerText = 'পুনরায় চালান ↺';
        isRunning = false;

      }, 5500);
    }

    function setNodeActive(nodeId) {
      const node = document.getElementById(nodeId);
      const circle = node.querySelector('div');
      circle.className = 'w-6 h-6 rounded-full bg-cyan-500 text-slate-950 font-bold text-[10px] flex items-center justify-center shadow-lg shadow-cyan-500/50 scale-110 transition-all duration-300';
      const label = node.querySelector('span');
      label.className = 'text-[11px] font-bold text-cyan-400 mt-2 transition-colors';
    }

    function animateNumber(elementId, start, end, suffix) {
      const el = document.getElementById(elementId);
      let current = start;
      const step = Math.ceil((end - start) / 25) || 1;
      const interval = setInterval(() => {
        current += step;
        if (current >= end) {
          current = end;
          clearInterval(interval);
        }
        el.innerText = current + suffix;
      }, 35);
    }

    function animateText(elementId, fullText) {
      const el = document.getElementById(elementId);
      el.innerText = fullText;
      el.classList.add('animate-pulse');
      setTimeout(() => el.classList.remove('animate-pulse'), 1000);
    }

    // Auto-play on load
    window.addEventListener('load', () => {
      setTimeout(runSimulation, 600);
    });
  </script>

</body>
</html>
