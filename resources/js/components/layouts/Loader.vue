<template>
    <div class="ai-loader-screen">
        <div class="loader-wrap">
            <div class="ring ring-one"></div>
            <div class="ring ring-two"></div>
            <div class="ring ring-three"></div>

            <div class="logo-box">
                <img src="/images/ai-logo.png" alt="AI بالعربي" />
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "LoaderComponent",
};
</script>

<style scoped>
.ai-loader-screen {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(circle at top left, rgba(43, 166, 222, 0.35), transparent 35%),
        linear-gradient(135deg, #154677 0%, #0b263f 45%, #2ba6de 100%);
}

.loader-wrap {
    position: relative;
    width: 180px;
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-box {
    width: 92px;
    height: 92px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
    z-index: 5;
}

.logo-box img {
    width: 70px;
    height: auto;
    display: block;
}

.ring {
    position: absolute;
    border-radius: 50%;
    border-style: solid;
    border-color: transparent;
}

.ring-one {
    width: 165px;
    height: 165px;
    border-width: 4px;
    border-top-color: #2ba6de;
    border-left-color: #2ba6de;
    animation: spin 1.1s linear infinite;
}

.ring-two {
    width: 135px;
    height: 135px;
    border-width: 3px;
    border-right-color: rgba(255, 255, 255, 0.7);
    border-bottom-color: rgba(255, 255, 255, 0.35);
    animation: spinReverse 1.5s linear infinite;
}

.ring-three {
    width: 195px;
    height: 195px;
    border-width: 3px;
    border-top-color: rgba(43, 166, 222, 0.45);
    border-bottom-color: rgba(21, 70, 119, 0.75);
    animation: spin 2s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@keyframes spinReverse {
    from {
        transform: rotate(360deg);
    }
    to {
        transform: rotate(0deg);
    }
}
</style>
