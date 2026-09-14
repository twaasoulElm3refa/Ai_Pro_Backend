export const PROGRAMMING_LANGUAGES = Object.freeze([
    "JavaScript", "TypeScript", "Python", "PHP", "Laravel", "Java", "C#", "Go", "Rust",
    "C++", "C", "Ruby", "Swift", "Kotlin", "Dart", "SQL", "Bash", "HTML", "CSS",
]);

export const PROGRAMMING_FRAMEWORKS = Object.freeze([
    "Express.js", "NestJS", "Laravel", "Django", "FastAPI", "Flask", "Spring Boot",
    "ASP.NET Core", "Next.js", "Nuxt.js", "React", "Vue.js", "Angular", "Flutter",
    "React Native", "Ruby on Rails",
]);

// The provider contract has one free-form programming_language field and no framework field.
export function programmingLanguageValue(language, framework) {
    return [language, framework].map((value) => String(value || "").trim()).filter(Boolean).join(" / ");
}
